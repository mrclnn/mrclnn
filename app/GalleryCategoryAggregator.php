<?php

namespace App;

use Illuminate\Support\Facades\DB;

class GalleryCategoryAggregator
{
    private static array $enabledCategories;
    public static function getEnabledCategories() : array
    {

        if(isset(self::$enabledCategories)) return self::$enabledCategories;
        $query = <<<QUERY
select 
    id
from categories
where
    enabled = 1
QUERY;
        $enabledCategoriesIDs = DB::select($query);
        foreach($enabledCategoriesIDs as $enabledCategory){
            $category = new GalleryCategoryModel();
            $category = $category->getCategoryFromId($enabledCategory->id);
            if($category) self::$enabledCategories[] = $category;
        }
        return self::$enabledCategories;
    }
}