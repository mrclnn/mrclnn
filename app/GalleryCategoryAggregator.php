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

    public static function getFromId(int $id): ?GalleryCategoryModel
    {
        $query = <<<QUERY
select *
from categories
where id = ?
QUERY;
        $categoryData = DB::select($query, [$id]);
        if (empty($categoryData)) return null;
        return (new GalleryCategoryModel())->fillFromDBData($categoryData[0]);

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

    public static function addCategory(string $categoryName, array $tags, int $count = 0): bool
    {
        $query = <<<QUERY
insert into categories
    (
     name,
     dir_name,
     tag,
     tag_alias,
     enabled,
     count,
     extend_tags,
     exclude_tags,
     include_tags
    )
values
    (
     ?,
     'dir_name',
     'tag',
     'tag_alias',
     1,
     ?,
     ?,
     ?,
     ?
    )
QUERY;
        $extendTags = $tags['extendTags'];
        $excludeTags = $tags['excludeTags'];
        $includeTags = $tags['includeTags'];
        DB::select($query, [$categoryName, $count, $extendTags, $excludeTags, $includeTags]);
        //todo дописать тут условие обработку ошибок
        return true;

    }

    public static function deleteCategory(int $categoryID): bool
    {
        //todo нужно не удалять а заполнять графу deleted_at и deleted
        $query = <<<QUERY
delete from categories where id = ?
QUERY;
        DB::select($query, [$categoryID]);
        //todo дописать тут условие обработку ошибок
        return true;

    }

    public static function updateCategory(int $categoryID): ?GalleryCategoryModel
    {
        $category = self::getFromId($categoryID);
        if(!$category) return null;
        $category->reCount();
        $query = <<<QUERY
update categories set count = ? where id = ?
QUERY;
        //todo дописать проверку на успешность
        DB::select($query, [$category->count, $categoryID]);
        return $category;
    }

    public static function checkAssociatedTagsCount(array $tags): int
    {
        $extendTags = $tags['extendTags'] ?? '';
        $excludeTags = $tags['excludeTags'] ?? '';
        $includeTags = $tags['includeTags'] ?? '';

        return self::getFakeCategory()->setTags($extendTags, $excludeTags, $includeTags)->reCount()->count;
    }

    private static function getFakeCategory(): GalleryCategoryModel
    {
        $fakeCategoryData = (object)[
            'id' => 0,
            'name' => 'Fake temporary category',
            'associatedTags' => [],
            'enabled' => false,
            'count' => 0,
        ];
        return (new GalleryCategoryModel())->fillFromDBData($fakeCategoryData);
    }


}