<?php

namespace App;

use App\Models\Categories;
use App\Models\Posts;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class GalleryPostAggregator
{

    private static Logger $logger;

    private static int $defaultChunkSize = 40;

    // Чем больше $sizeDiffusionLimit тем больше допуск к размеру экрана, тем больше возможные поля вокруг изображения.
    // Чем меньше $sizeDiffusionLimit тем точнее изображение будет соответствовать размеру экрана.

    // todo подтягивать всю конфигурационную инфо из базы данных
    private static int $sizeDiffusionLimit = 3;

    private static float $screen = 1;

    private static int $offset = 0;

    private static bool $order = false;

    public static function countAll(): int
    {
        return Posts::whereNotIn('status', [0,9])->count();
    }

    public static function getFromFileName(string $fileName): ?GalleryPostModel
    {
        $query = <<<QUERY
SELECT * FROM posts WHERE file_name = ?
QUERY;
        $postData = DB::select($query, [$fileName]);
        return (new GalleryPostModel())->fillByDBData($postData[0]);
    }

    public static function checkExistence(array $remoteIdList): array
    {
        $remoteId = implode(',', $remoteIdList);
        if(empty($remoteId)) return [];
        $existedPostID = DB::select("select post_id from posts where post_id in ($remoteId)");
        return array_map(function($post){
            return $post->post_id;
        }, $existedPostID);
    }

    public static function setDuplicates($duplicates)
    {
        return DB::table('posts')
            ->whereIn('id', $duplicates)
            ->update(['status' => 9]);
    }

    public static function getPosts(GalleryCategoryModel $category, array $config = []): array
    {
        //todo нужно нормализовать получаемый config объект
        self::setConfig($config);
        self::$logger->info('Requested category: ', [$category->name]);

        $beg = microtime(true);

        if(!empty($category->id) and self::$order = false){
            $posts = (Categories::query()->find($category->id))->getPostsForSlider(40, self::$screen);
            $res = array_map(function ($postData) {
                return (new GalleryPostModel())->fillByDBData((object)$postData);
            }, $posts->toArray());
            self::$logger->info('Time: '.(microtime(true) - $beg).' sec');
            return $res;
        }

        // Это фильтры по умолчанию для отображения в слайдере.
        // Например, лимит в N постов и показывать только shown = 0 и сортировать чтобы status = 2 был в конце.
        // Однако может понадобиться взять все посты избегая этих фильтров
//        $query = DB::table('posts')->where('shown', 0);
        $query = self::setFilters($category, DB::table('posts'));

//        dd($query->toSql());
        //todo возможно не стоит ресетить сразу. отдать сначала всю категорию и только потом ресетить
        // в таком случае нужно выдавать исключительно те посты которых еще не было показано
        if(self::needResetShown(clone $query)) self::resetShown(clone $query);

        $query = self::setOrder($query);
        $query = self::setLimits($category, $query);

        $postsData = $query->get()->toArray();
        return array_map(function ($postData) {
            return (new GalleryPostModel())->fillByDBData($postData);
        }, $postsData);

    }

//    public static function disableAbandonedPosts(): int
//    {
//        $abandonedTags = GalleryTagAggregator::getDisabledTags();
//        $fakeCategory = GalleryCategoryAggregator::getFakeCategory();
//        $fakeCategory->extendTags = $abandonedTags;
//        $posts = self::getPosts($fakeCategory);
////        $img = array_map(function($post){
////            $path = '/img/' . $post->fileName;
////            return "<img src='$path'>";
////        }, $posts);
////        echo implode('', $img);
////        dd('');
//        $countOfDisabled = 0;
//        foreach($posts as $post){
//            if($post->disable() === true) $countOfDisabled++;
//        }
//        return $countOfDisabled;
//    }

    private static function needResetShown(Builder $query): ?bool
    {
        //todo а объект Builder передается по значению или по ссылке?
        $query->where('shown', '=', 0);
        $notShownPostsCount = $query->get()->count();
        self::$logger->info("$notShownPostsCount fresh posts rest. chunk size: ".self::$defaultChunkSize);
        return $notShownPostsCount < self::$defaultChunkSize;
    }

    private static function resetShown(Builder $query): void
    {
        //todo а объект Builder передается по значению или по ссылке?
        self::$logger->info('resetting shown...');
        $query->update(['shown' => 0]);
    }

    private static function setFilters(GalleryCategoryModel $category, Builder $query): Builder
    {
        //todo если во время одной сессии работать с разными категориями то фильтры будут затираться, добавить фильтры по категориям
//        if(!empty(self::$filterQuery)) return self::$filterQuery;

        if(!empty($category->requiredStatus)) {
            $query->whereIn('status', $category->requiredStatus);
        } else if(!empty($category->exceptionStatus)){
            // Отключать тэги нужно только в том случае если не указан status который мы хотим получить.
            // Например, получить только 2, но не хотим 0 или 9, первое условие автоматически исключает второе.
            $query->whereNotIn('status', $category->exceptionStatus);
        }

        if(!empty($category->extendTags)){
            $query->where(function($query) use ($category)
            {
                foreach($category->extendTags as $extendTag){
                    $query->orWhereRaw('concat(",", tags, ",") like concat("%,", ?, ",%")', [$extendTag->id]);
                }
            });
        }

        if(!empty($category->excludeTags)){
            $query->where(function($query) use ($category)
            {
                foreach($category->excludeTags as $excludeTag){
                    $query->whereRaw('concat(",", tags, ",") not like concat("%,", ?, ",%")', [$excludeTag->id]);
                }
            });
        }

        if(!empty($category->includeTags)){
            $query->where(function($query) use ($category)
            {
                foreach($category->includeTags as $includeTag){
                    $query->whereRaw('concat(",", tags, ",") like concat("%,", ?, ",%")', [$includeTag->id]);
                }
            });
        }

        return $query;
    }

    private static function setOrder(Builder $query)
    {
        if(self::$order){
            $query->orderBy('hash');
        } else {
            $query->orderByRaw(
                'status, ROUND((ROUND(ABS((width/height) - ?), 1) / ?), 1), rand()',
                [self::$screen, self::$sizeDiffusionLimit]
            );
        }
        return $query;
    }

    private static function setLimits(GalleryCategoryModel $category, Builder $query): Builder
    {
        if(self::$defaultChunkSize > 0) $query->limit(self::$defaultChunkSize);
//        if(self::$offset > 0) $query->offset(self::$offset);
        return $query;
    }

    private static function setConfig(array $config): void
    {
        //todo тут все очень плохо, нужен объект конфиг
        if (empty(self::$logger)) self::setLogger();

        if(empty($config)) self::$defaultChunkSize = 0;

        if (isset($config['screen'])) {
            self::$screen = (float)$config['screen'];
        }
//        if (isset($config['offset'])) {
//            self::$offset = (int)$config['offset'];
//        }
        if (isset($config['order'])) {
            self::$order = true;
            self::$defaultChunkSize = 0;
        }
    }

    private static function setLogger()
    {
        //todo недостаточная проверка правильности logger. стоит проверить хотя бы класс объекта
        self::$logger = new Logger(
            'helper', [
            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/helper.log'), 14, Logger::DEBUG, true, 0664),
        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}