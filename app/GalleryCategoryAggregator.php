<?php

namespace App;

use Illuminate\Support\Facades\DB;

class GalleryCategoryAggregator
{
    private static array $enabledCategories;

    public static function getEnabledCategories(): array
    {

        if (isset(self::$enabledCategories)) return self::$enabledCategories;
        self::$enabledCategories = [];

        $query = <<<QUERY
select *
from categories
where
    enabled = 1
QUERY;
        $enabledCategoriesData = DB::select($query);
        foreach ($enabledCategoriesData as $enabledCategoryData) {
            self::$enabledCategories[] = (new GalleryCategoryModel())->fillFromDBData($enabledCategoryData);
        }
        return self::$enabledCategories;
    }

    public static function getFromName(string $name): ?GalleryCategoryModel
    {
        $query = <<<QUERY
select *
from categories
where name = ?
QUERY;
        $categoryData = DB::select($query, [$name]);
        if (empty($categoryData)) return null;
        //todo по идее он может получить больше 1 строчки, т.к. имя не уникальное поле
        return (new GalleryCategoryModel())->fillFromDBData($categoryData[0]);

    }


    public static function checkAssociatedTagsCount(array $tags): int
    {
        $extendTags = $tags['extendTags'] ?? '';
        $excludeTags = $tags['excludeTags'] ?? '';
        $includeTags = $tags['includeTags'] ?? '';

        return self::getFakeCategory()->setTags($extendTags, $excludeTags, $includeTags)->reCount()->count;
    }

    public static function getFakeCategory(): GalleryCategoryModel
    {
        return new GalleryCategoryModel();
    }


}