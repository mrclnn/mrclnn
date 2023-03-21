<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use phpDocumentor\Reflection\Types\This;

class GalleryPostAggregator{

    // todo возможно стоит оставить это абстрактным классом

    private static int $postsChunkSize = 40;

    // чем больше $sizeDiffusionLimit тем больше допуск к размеру экрана. тем больше возможные поля вокруг изображения.
    // чем меньше $sizeDiffusionLimit тем точнее изображение будет соответствовать размеру экрана.
    private static int $sizeDiffusionLimit = 3;

    private static float $screen = 1;

    private static int $offset = 0;



    public static function countAll(){
        $query = <<<COUNTALL
select count(id) as sum from posts
COUNTALL;
        return DB::select($query)[0]->sum;
    }

    public static function getPosts(GalleryCategoryModel $category, array $config = []){
        self::setConfig($config);
        $logger = new Logger(
            'helper', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/helper.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );

        $logger->info('category: ', [$category->name]);

        $filter = '';
        if($category->associatedTags){
            $logger->info('associated tags: ', [$category->associatedTags]);
            foreach($category->associatedTags as $tagID){
                $filter .= "concat(',',tags,',') like '%,$tagID,%' and ";
            }
        }
        $filter .= 'true';

        $query = <<<QUERY
select
    count,
    file_name,
    status,
    size,
    shown,
    width,
    height,
    tags_artist as artists,
    tags_character,
    tags_copyright
from
(select
    (select count(*) from posts where true and
                                    # PLACE FOR WHERE #
                                    ) as count,
    file_name,
    status,
    ROUND((ROUND(ABS((width/height) - ?), 1) / ?), 1) AS size,
    shown,
    width,
    height,
    tags_artist,
    tags_character,
    tags_copyright
from posts
where 
    true and
    # PLACE FOR WHERE #
order by size, shown, rand()) as allPosts
limit ?
offset ?
QUERY;
        $sql = preg_replace('/# PLACE FOR WHERE #/', $filter, $query);



        return DB::select($sql, [self::$screen, self::$sizeDiffusionLimit, self::$postsChunkSize, self::$offset]);
    }
    private static function setConfig(array $config){
        if(isset($config['screen'])){
            self::$screen = (float)$config['screen'];
        }
        if(isset($config['offset'])){
            self::$offset = (int)$config['offset'];
        }
    }
}