<?php

namespace App;

use Illuminate\Support\Facades\DB;

class GalleryTagModel
{
    public ?int $id = null;
    public string $type;
    public string $tag;
    public int $count;
    public bool $enabled;
    public function getTagFromName(string $name) : ?GalleryTagModel
    {
        $query = <<<QUERY
select
id,
type,
tag,
count,
enabled
from tags
where tag = ?
QUERY;
        $params = [$name];
        return $this->getTag($query, $params);
    }
    public function getTagFromId(int $id) : ?GalleryTagModel
    {
        $query = <<<QUERY
select
id,
type,
tag,
count,
enabled
from tags
where id = ?
QUERY;
        $params = [$id];
        return $this->getTag($query, $params);

    }

    public function getFromData(object $data) : GalleryTagModel
    {
        // подразумевается что object $data это полученное из базы данных представление в Aggregator
        $this->id = $data->id ?? null;
        $this->type = $data->type ?? null;
        $this->tag = $data->tag ?? null;
        $this->count = $data->count ?? null;
        $this->enabled = $data->enabled ?? null;

        return $this;
    }



    private function getTag(string $query, array $params) : ?GalleryTagModel
    {
        $res = DB::select($query, $params);
        if(empty($res)) return null;

        $this->id = (int)$res[0]->id;
        $this->type = (string)$res[0]->type;
        $this->tag = (string)$res[0]->tag;
        $this->count = (int)$res[0]->count;
        $this->enabled = (bool)$res[0]->enabled;

        return $this;
    }


}