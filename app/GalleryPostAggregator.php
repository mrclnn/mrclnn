<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class GalleryPostAggregator
{

    // todo возможно стоит оставить это абстрактным классом

    private static Logger $logger;

    private static int $postsChunkSize;

    // Чем больше $sizeDiffusionLimit тем больше допуск к размеру экрана, тем больше возможные поля вокруг изображения.
    // Чем меньше $sizeDiffusionLimit тем точнее изображение будет соответствовать размеру экрана.
    private static int $sizeDiffusionLimit = 3;

    private static float $screen = 1;

    private static int $offset;

    public static function countAll(): int
    {
        return (int)(DB::select('select count(*) as count from posts where status != 0')[0]->count);
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
        self::$logger->info('category: ', [$category->name]);

        $filter = self::getFilters($category);
        $limit = isset(self::$postsChunkSize) ? "LIMIT " . self::$postsChunkSize : '';
        $offset = isset(self::$offset) ? "OFFSET " . self::$offset : '';

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

    private static function getFilters(GalleryCategoryModel $category): string
    {

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
        return "and $extendFilter and $excludeFilter and $includeFilter";

    }

    private static function setConfig(array $config): void
    {
        if (empty(self::$logger)) self::setLogger();

        if (isset($config['screen'])) {
            self::$screen = (float)$config['screen'];
        }
        if (isset($config['offset'])) {
            self::$offset = (int)$config['offset'];
        }
        if (isset($config['chunkSize'])) {
            self::$postsChunkSize = (int)$config['chunkSize'];
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