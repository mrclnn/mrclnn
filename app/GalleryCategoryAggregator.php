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

    public static function addCategory(string $categoryName, string $associatedTags) : bool
    {
        $query = <<<QUERY
insert into categories
    (
     name,
     dir_name,
     tag,
     tag_alias,
     enabled,
     associated_tags
    )
values
    (
     ?,
     'dir_name',
     'tag',
     'tag_alias',
     1,
     ?
    )
QUERY;
        DB::select($query, [$categoryName, $associatedTags]);
        //todo дописать тут условие обработку ошибок
        return true;

    }

    public static function checkAssociatedTagsCount(string $associatedTags) : int
    {
        $preCategory = new GalleryCategoryModel();
        $preCategory->getFakeCategory($associatedTags);
        return $preCategory->count;

    }

    public static function deleteCategory(int $categoryID) : bool
    {
        $query = <<<QUERY
delete from categories where id = ?
QUERY;
        DB::select($query, [$categoryID]);
        //todo дописать тут условие обработку ошибок
        return true;

    }
}