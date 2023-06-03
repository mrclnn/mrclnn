<?php

namespace App;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GalleryTagAggregator
{
    public static function searchTag(string $searchWord) : ?array
    {
        $query = <<<QUERY
select * from tags where tag like '%$searchWord%'
QUERY;
        $tagsData = DB::select($query);
        $tags = [];
        foreach($tagsData as $tagData){
            $tags[] = (new GalleryTagModel())->fillFromData($tagData);
        }
        return $tags;

    }

    public static function getFromIDs(array $idList) : array
    {
        if(empty($idList)) return [];
        $idList = implode(',', $idList);
        $query = "select *
from tags
where id in ($idList)";
        $tagsInfo = DB::select($query, [$idList]);
        return self::getTags($tagsInfo);

    }

    public static function getFromName(string $name) : ?GalleryTagModel
    {
        $dbData = DB::table('tags')
            ->where('tag', '=', $name)
            ->get()
            ->toArray();
        if(count($dbData) === 1){
            return (new GalleryTagModel())->fillFromData($dbData[0]);
        }
        return null;
//        throw new InvalidArgumentException("requested category $name not found");
    }

    public static function getAuthors() : array
    {
        //todo тут проверки
        return DB::table('tags')
            ->where('type', '=', 'artist')
            ->where('id', '!=', 52) // не выбираем stable_diffusion
            ->get()
            ->toArray();
    }

    public static function getDisabledTags(): array
    {
        $disabledTagsInfo = DB::select("select * from tags where enabled = 0");
        return self::getTags($disabledTagsInfo);
    }
    private static function getTags(array $tagsInfo): array
    {
        return array_map(function($tagInfo){
            return (new GalleryTagModel())->fillFromData($tagInfo);
        }, $tagsInfo);
    }
}