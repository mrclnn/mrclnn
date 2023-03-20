<?php

namespace App;

use Illuminate\Support\Facades\DB;

class GalleryCategoryAggregator
{
    public static function getEnabledCategories() : array
    {
        $query = <<<QUERY
select 
    name
from categories
where
    enabled = 1
QUERY;
        $enabledCategories = DB::select($query);
        $namesOfEnabledCategories = [];
        foreach($enabledCategories as $enabledCategory){
            $namesOfEnabledCategories[] = $enabledCategory->name;
        }
        return $namesOfEnabledCategories;
    }
    public static function getAssociatedTags(string $category) : ?array
    {
        $query = <<<QUERY
select
    associated_tags
from categories
where
    enabled = 1 and
    name = ?
QUERY;
        $tagsString = DB::select($query, [$category])[0]->associated_tags;
        return $tagsString ? explode(',', $tagsString) : null;

    }
}