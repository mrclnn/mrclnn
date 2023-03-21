<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GalleryCategoryModel extends Model
{

    public int $id;
    public string $name;
    public array $associatedTags;
    public bool $enabled;
    //todo заменить это полем базы данных
    public int $count;
    public function getCategoryFromId(int $id) :?GalleryCategoryModel
    {
        $query = <<<QUERY
select
    id,
    name,
    associated_tags,
    enabled
from categories
where id = ?
QUERY;
        $params = [$id];
        return $this->getCategory($query, $params);

    }
    public function getCategoryFromName(string $name) :?GalleryCategoryModel
    {
        $query = <<<QUERY
select
    id,
    name,
    associated_tags,
    enabled
from categories
where name = ?
QUERY;
        $params = [$name];
        return $this->getCategory($query, $params);

    }

    public function countPosts(){
        if(!isset($this->id)) return 0;
        $posts = GalleryPostAggregator::getPosts($this);
        return count($posts);
    }
    private function getCategory(string $query, array $params) : ?GalleryCategoryModel
    {
        $res = DB::select($query, $params);
        if(empty($res)) return null;

        $this->id = (int)$res[0]->id;
        $this->name = (string)$res[0]->name;
        $this->associatedTags = $res[0]->associated_tags ? explode(',', (string)$res[0]->associated_tags) : [];
        $this->enabled = (bool)$res[0]->enabled;
        $this->count = $this->countPosts();

        return $this;
    }
}
