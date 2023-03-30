<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class GalleryPostAggregator
{

    // todo выполняя неоднократно сложный sql запрос для разных целей возможно лучше было бы
    // todo записывать результат выборки в отдельную временную таблицу или что-то вроде того

    // todo дублирование кода при конструировании sql запроса в нескольких методах, переписать используя laravel инструменты для построения запросов

    private static Logger $logger;

    private static int $defaultChunkSize = 40;

    // Чем больше $sizeDiffusionLimit тем больше допуск к размеру экрана, тем больше возможные поля вокруг изображения.
    // Чем меньше $sizeDiffusionLimit тем точнее изображение будет соответствовать размеру экрана.

    // todo подтягивать всю конфигурационную инфо из базы данных
    private static int $sizeDiffusionLimit = 3;

    private static float $screen = 1;

    private static int $offset = 0;

    private static string $filterQuery = '';

    public static function countAll(): int
    {
        return (int)(DB::select('select count(*) as count from posts where status != 0')[0]->count);
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

    public function getById(int $id): ?GalleryPostModel
    {

        $query = <<<QUERY
select
    id,
    category_id,
    status,
    file_name,
    width,
    height,
    shown,
    size,
    created_at,
    original_uri,
    post_id,
    estimate_at,
    hash,
    tags
from posts
where id = ?
QUERY;
        $postData = DB::select($query, [$id]);
        if (empty($postData)) return null;

        return (new GalleryPostModel())->fillByDBData($postData[0]);

    }

    public static function getPosts(GalleryCategoryModel $category, array $config = []): array
    {
        //todo по идее тут тоже должен возвращаться массив объектов postModel
        self::setConfig($config);
        self::$logger->info('Requested category: ', [$category->name]);

        if(self::needResetShown($category)) self::resetShown($category);

        $filter = self::getCategoryFilters($category);
        $limit = self::$defaultChunkSize > 0 ? "LIMIT " . self::$defaultChunkSize : '';
        $offset = self::$offset > 0 ? "OFFSET " . self::$offset : '';

        //todo возможно нужно конструировать еще и order
        //todo Это все охуеть как не безопасно
        $query = <<<QUERY
select * from
(select * from posts
where status != 0
# PLACE FOR WHERE #
order by shown, ROUND((ROUND(ABS((width/height) - ?), 1) / ?), 1), rand()) as allPosts
# PLACE FOR LIMIT #
# PLACE FOR OFFSET #
QUERY;
        $sql = preg_replace('/# PLACE FOR WHERE #/', $filter, $query);
        $sql = preg_replace('/# PLACE FOR LIMIT #/', $limit, $sql);
        $sql = preg_replace('/# PLACE FOR OFFSET #/', $offset, $sql);

        $postsData = DB::select($sql, [self::$screen, self::$sizeDiffusionLimit]);
        return array_map(function ($postData) {
            return (new GalleryPostModel())->fillByDBData($postData);
        }, $postsData);

    }

    private static function needResetShown(GalleryCategoryModel $category): ?bool
    {
        $filter = self::getCategoryFilters($category);
        $query = <<<QUERY
select 
count(*) as count
from posts 
where shown = 0
# PLACE FOR WHERE #                                
QUERY;
        $query = preg_replace('/# PLACE FOR WHERE #/', $filter, $query);
        $shownData = DB::select($query);
        if(empty($shownData)) return null;
        $countOfNotShown = (int)$shownData[0]->count;
        self::$logger->info("$countOfNotShown fresh posts rest. chunk size: ".self::$defaultChunkSize);
        return $countOfNotShown < self::$defaultChunkSize;


    }

    private static function resetShown(GalleryCategoryModel $category): void
    {
        self::$logger->info('resetting shown...');
        $filter = self::getCategoryFilters($category);
        $query = <<<QUERY
update posts set shown = 0 where shown = 1 
# PLACE FOR WHERE #
QUERY;
        $query = preg_replace('/# PLACE FOR WHERE #/', $filter, $query);
        DB::select($query);
        //todo дописать обработку ошибок

    }

    private static function getCategoryFilters(GalleryCategoryModel $category): string
    {
        if(!empty(self::$filterQuery)) return self::$filterQuery;

        if (!empty($category->extendTags)) {
            $extendFilter = array_map(function ($tagID) {
                return "concat(',', tags, ',') like '%,$tagID,%'";
            }, $category->extendTags);
            $extendFilter = implode(' OR ', $extendFilter);
            $extendFilter = "($extendFilter)";

        } else {
            $extendFilter = 'true';
        }

        if (!empty($category->excludeTags)) {

            $excludeFilter = array_map(function ($tagID) {
                return "concat(',', tags, ',') not like '%,$tagID,%'";
            }, $category->excludeTags);
            $excludeFilter = implode(' AND ', $excludeFilter);
            $excludeFilter = "($excludeFilter)";

        } else {
            $excludeFilter = 'true';
        }

        if (!empty($category->includeTags)) {

            $includeFilter = array_map(function ($tagID) {
                return "concat(',', tags, ',') like '%,$tagID,%'";
            }, $category->includeTags);
            $includeFilter = implode(' AND ', $includeFilter);
            $includeFilter = "($includeFilter)";

        } else {
            $includeFilter = 'true';
        }
        self::$filterQuery = "and $extendFilter and $excludeFilter and $includeFilter";
        return self::$filterQuery;

    }

    private static function setConfig(array $config): void
    {
        if (empty(self::$logger)) self::setLogger();

        if(empty($config)) self::$defaultChunkSize = 0;

        if (isset($config['screen'])) {
            self::$screen = (float)$config['screen'];
        }
        if (isset($config['offset'])) {
            self::$offset = (int)$config['offset'];
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