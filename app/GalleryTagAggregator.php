<?php

namespace App;

use Illuminate\Support\Facades\DB;

class GalleryTagAggregator
{
    public static array $allTags;
    public static function getAllTags() : array
    {
        if(isset(self::$allTags)) return self::$allTags;
        $query = <<<QUERY
select
id
from tags
QUERY;
        $allTagsIds = DB::select($query);
        foreach($allTagsIds as $tagId){
            $tag = new GalleryTagModel();
            $tag->getTagFromId($tagId->id);
            self::$allTags[] = $tag;
        }
        return self::$allTags;

    }

    public static function searchTag(string $searchWord) : ?array
    {
        $query = <<<QUERY
select id from tags where tag like '%$searchWord%'
QUERY;
        $matchedTags = DB::select($query);
        $tags = [];
        foreach($matchedTags as $matchedTag){
            $tag = new GalleryTagModel();
            $tag->getTagFromId($matchedTag->id);
            $tags[] = $tag;
        }
        return $tags;

    }
}