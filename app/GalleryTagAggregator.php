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

    public static function addTag(string $type, string $tag): int
    {
        $query = <<<QUERY
insert into tags (type, tag) values (?, ?)
QUERY;
        DB::select($query, [$type, $tag]);
        $query = <<<QUERY
select id from tags where type = ? and tag = ?
QUERY;
        return (int)(DB::select($query, [$type, $tag])[0]->id);
    }

    public static function checkTags(array $tags): array
    {
        $filter = [];
        $requestedTags = [];
        foreach($tags as $type => $typedTags) {
            foreach ($typedTags as $tag) {
                $requestedTags[$tag] = $type;
                $escapedTag = str_replace("'", "\'", $tag);
                $filter[] = "(type = '$type' and tag = '$escapedTag')";

            }
        }

        $filter = implode(' or ', $filter);
        $query = <<<QUERY
select
    id, type, tag
    from tags
where TRUE
# PLACE FOR WHERE #
QUERY;
        $query = str_replace('# PLACE FOR WHERE #', "and $filter", $query);
        $existedTags = DB::select($query);

        $existedCount = count($existedTags);
        $requestedCount = count($tags, COUNT_RECURSIVE) - count($tags);

        $newTagsIDs = [];
        if($existedCount !== $requestedCount){
            $foundedTags = array_map(function($tag){return $tag->tag;}, $existedTags);
            $newTags = array_diff(array_keys($requestedTags), $foundedTags);

//            dd(['new tags' => $newTags, 'founded' => $foundedTags, 'requested' => array_keys($requestedTags)]);

            foreach($newTags as $tag){
                $newTagsIDs[] = self::addTag($requestedTags[$tag], $tag);
            }
        }

        return array_merge($newTagsIDs, array_map(function($tag){return $tag->id;}, $existedTags));
    }
}