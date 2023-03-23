<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GalleryCategoryModel extends Model
{

    public int $id;
    public string $name;
    public bool $enabled;
    public int $count;

    public array $associatedTags;
    public array $excludeTags;
    public array $extendTags;
    public array $includeTags;

    public function fillFromDBData(object $data): GalleryCategoryModel
    {
        $this->id = isset($data->id) ? (int)$data->id : null;
        $this->name = isset($data->name) ? (string)$data->name : null;
        $this->enabled = isset($data->enabled) ? (bool)$data->enabled : null;
        $this->count = isset($data->count) ? (int)$data->count : 0;

        $extendTags = isset($data->extend_tags) ? (string)$data->extend_tags : '';
        $excludeTags = isset($data->exclude_tags) ? (string)$data->exclude_tags : '';
        $includeTags = isset($data->include_tags) ? (string)$data->include_tags : '';

        $this->setTags($extendTags, $excludeTags, $includeTags);
//        $associatedTags = isset($data->associated_tags) ? (string)$data->associated_tags : '';
//        $this->setAssociatedTags($associatedTags);

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

    public function setAssociatedTags(string $associatedTags): GalleryCategoryModel
    {
        // todo проверка на правильность строки, должна быть строка состоящая исключительно из цифр и запятых
        $this->associatedTags = empty($associatedTags) ? [] : explode(',', $associatedTags);
        return $this;
    }

    public function setTags(string $extendTags, string $excludeTags, string $includeTags): GalleryCategoryModel
    {
        $this->extendTags = $this->isValidString($extendTags) ? explode(',', $extendTags) : [];
        $this->excludeTags = $this->isValidString($excludeTags) ? explode(',', $excludeTags) : [];
        $this->includeTags = $this->isValidString($includeTags) ? explode(',', $includeTags) : [];

        return $this;
    }

    private function isValidString(string $string): bool
    {
        return preg_match('/^[\d,]+$/', trim($string)) === 1;
    }


}
