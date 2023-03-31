<?php

namespace App;

use Illuminate\Support\Facades\DB;

class GalleryCategoryModel
{
    public int $id = 0;
    public string $name = '';
    public bool $enabled = false;
    public int $count = 0;

    public array $excludeTags = [];
    public array $extendTags = [];
    public array $includeTags = [];

    public function fillFromDBData(object $data): GalleryCategoryModel
    {
        $this->id = isset($data->id) ? (int)$data->id : 0;
        $this->name = isset($data->name) ? (string)$data->name : '';
        $this->enabled = isset($data->enabled) && (bool)$data->enabled;
        $this->count = isset($data->count) ? (int)$data->count : 0;

        $extendTags = isset($data->extend_tags) ? (string)$data->extend_tags : '';
        $excludeTags = isset($data->exclude_tags) ? (string)$data->exclude_tags : '';
        $includeTags = isset($data->include_tags) ? (string)$data->include_tags : '';

        $this->setTags($extendTags, $excludeTags, $includeTags);

        return $this;

    }

    public function reCount(): GalleryCategoryModel
    {
        if (!isset($this->id)) return $this;
        if (empty($this->excludeTags) and empty($this->extendTags) and empty($this->includeTags)) {
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

    private function isValidString(string $string): bool
    {
        return preg_match('/^[\d,]+$/', trim($string)) === 1;
    }

    public function isValid(){
        //todo здесь проверка целостности объекта, например tags свойства должны быть массивами содержащими TagModel и ничто иное и тд
    }

    public function update(): GalleryCategoryModel
    {
        $this->reCount();
        $query = <<<QUERY
update categories set count = ? where id = ?
QUERY;
        //todo дописать проверку на успешность
        DB::select($query, [$this->count, $this->id]);
        return $this;
    }


}
