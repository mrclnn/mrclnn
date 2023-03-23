<?php

namespace App;

use Illuminate\Support\Facades\DB;

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

    public static function getFromIDs(array $idList) : ?array
    {
        if(empty($idList)) return null;
        $idList = implode(',', $idList);
        $query = "select *
from tags
where id in ($idList)";
        $tagsInfo = DB::select($query, [$idList]);
        $tags = [];
        foreach($tagsInfo as $tagInfo){
            $tags[] = (new GalleryTagModel())->fillFromData($tagInfo);
        }
        return $tags;

    }
}