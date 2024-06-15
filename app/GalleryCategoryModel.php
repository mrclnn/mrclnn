<?php

namespace App;

use Illuminate\Support\Facades\DB;

class GalleryCategoryModel
{
    public int $id = 0;
    public string $name = '';
    public bool $enabled = true;
    public int $count = 0;

    public array $requiredStatus = [];
    public array $exceptionStatus = [];
    public array $extendTags = [];
    public array $excludeTags = [];
    public array $includeTags = [];

    public function fillFromDBData(object $data): GalleryCategoryModel
    {
        $this->id = isset($data->id) ? (int)$data->id : 0;
        $this->name = isset($data->name) ? (string)$data->name : '';
        $this->enabled = isset($data->enabled) && (bool)$data->enabled;
        $this->count = isset($data->count) ? (int)$data->count : 0;

        $requiredStatus = isset($data->required_status) ? (string)$data->required_status : '';
        $exceptionStatus = isset($data->exception_status) ? (string)$data->exception_status : '';
        $extendTags = isset($data->extend_tags) ? (string)$data->extend_tags : '';
        $excludeTags = isset($data->exclude_tags) ? (string)$data->exclude_tags : '';
        $includeTags = isset($data->include_tags) ? (string)$data->include_tags : '';

        $this->setStatus($requiredStatus, $exceptionStatus);
        $this->setTags($extendTags, $excludeTags, $includeTags);

        return $this;

    }

    public function getFromID(int $id): ?GalleryCategoryModel
    {
        //todo проверку на наличие в базе такого ID
        $query = <<<QUERY
select *
from categories
where id = ?
QUERY;
        $categoryData = DB::select($query, [$id]);
        //todo возвращать пустой объект а не Null
        if (empty($categoryData)) return null;
        $this->fillFromDBData($categoryData[0]);
        return $this;
    }

    public function reCount(): GalleryCategoryModel
    {
        if ($this->name === 'All') {
            $this->count = GalleryPostAggregator::countAll();
            return $this;
        }
        $posts = GalleryPostAggregator::getPosts($this);
        $this->count = count($posts);
        return $this;
    }

    public function setTags(string $extendTags, string $excludeTags, string $includeTags): GalleryCategoryModel
    {
        $extendTags = $this->isValidString($extendTags) ? explode(',', $extendTags) : [];
        $excludeTags = $this->isValidString($excludeTags) ? explode(',', $excludeTags) : [];
        $includeTags = $this->isValidString($includeTags) ? explode(',', $includeTags) : [];

        $this->extendTags = GalleryTagAggregator::getFromIDs($extendTags);
        $this->excludeTags = GalleryTagAggregator::getFromIDs($excludeTags);
        $this->includeTags = GalleryTagAggregator::getFromIDs($includeTags);

        return $this;
    }
    private function setStatus(string $requiredStatus, string $exceptionStatus)
    {
        $this->requiredStatus = $this->isValidString($requiredStatus) ? explode(',', $requiredStatus) : [];
        $this->exceptionStatus = $this->isValidString($exceptionStatus) ? explode(',', $exceptionStatus) : [];

    }

    private function isValidString(string $string): bool
    {
        return preg_match('/^[\d,]+$/', trim($string)) === 1;
    }

    private function getExtendTags(): string
    {
        $extendTagsIds = array_map(function($tag){
            return $tag->id;
        }, $this->extendTags);
        return implode(',', $extendTagsIds);
    }
    private function getExcludeTags(): string
    {
        $excludeTagsIds = array_map(function($tag){
            return $tag->id;
        }, $this->excludeTags);
        return implode(',', $excludeTagsIds);
    }
    private function getIncludeTags(): string
    {
        $includeTagsIds = array_map(function($tag){
            return $tag->id;
        }, $this->includeTags);
        return implode(',', $includeTagsIds);
    }


    public function update(): GalleryCategoryModel
    {
        // обновляет в базе данных все поля исходя из данных которыми заполнен объект
        $this->reCount();
        $categoryFromDB = (new GalleryCategoryModel())->getFromID($this->id);
        $changedProperties = $this->compare($categoryFromDB);
        $needToUpdateProperties = $this->getPropertiesToDB($changedProperties);

        if(empty($needToUpdateProperties)) return $this;
        //todo проверка возвращаемого значения
        DB::table('categories')
            ->where('id', $this->id)
            ->update($needToUpdateProperties);
        return $this;
    }
    public function insert(): GalleryCategoryModel
    {
        $insert = $this->getPropertiesToDB();
        if(isset($insert['id'])) unset($insert['id']);
        //todo проверка возвращаемого значения
        DB::table('categories')
            ->insert($insert);
        return $this;
    }
    public function delete()
    {
        //todo проверку
        DB::table('categories')
            ->where('id', $this->id)
            ->delete();
    }

    private function getPropertiesToDB(array $properties = []): array
    {
        $result = [];
        $properties = !empty($properties) ? $properties : array_keys(get_object_vars($this));
        foreach($properties as $property){
            if(property_exists($this, $property)){
                switch ($property){
                    case 'extendTags':
                        $result['extend_tags'] = $this->getExtendTags();
                        break;
                    case 'excludeTags':
                        $result['exclude_tags'] = $this->getExcludeTags();
                        break;
                    case 'includeTags':
                        $result['include_tags'] = $this->getIncludeTags();
                        break;
                    case 'requiredStatus':
                        $result['required_status'] = implode(',', $this->requiredStatus);
                        break;
                    case 'exceptionStatus':
                        $result['exception_status'] = implode(',', $this->exceptionStatus);
                        break;
                    default:
                        $result[$this->toSnakeCase($property)] = $this->$property;
                }
            }
        }
        //todo костыль пиздец
        if($result['exception_status'] === '') unset($result['exception_status']);
        return $result;
    }
    private function compare(GalleryCategoryModel $example): array
    {
        $diff = [];
        //todo возможно тут стоит циклом пройтись просто через все свойства объекта
        // однако в таком случае мы не знаем как сравнивать свойства,
        // для сравнения массивов тегов мы используем функции приводящие их к виду строки перечисляющей id
//        $propertyNameList = get_object_vars($this);
//        foreach($propertyNameList as $propertyName){
//            if(!property_exists($example, $propertyName)){
//                $diff[] = $propertyName;
//            } else if($this->$propertyName ) {
//
//            }
//
//        }
        if($this->name !== $example->name) $diff[] = 'name';
        if($this->count !== $example->count) $diff[] = 'count';
        if($this->getExtendTags() !== $example->getExtendTags()) $diff[] = 'extendTags';
        if($this->getExcludeTags() !== $example->getExcludeTags()) $diff[] = 'excludeTags';
        if($this->getIncludeTags() !== $example->getIncludeTags()) $diff[] = 'includeTags';
        //todo структура возвращаемого значения оставляет желать лучшего
        return $diff;
    }
    private function toSnakeCase(string $name): string
    {
        //todo проверку на то какая строка получена. возможно это не camelCase
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }


}
