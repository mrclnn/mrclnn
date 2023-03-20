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
        $res = DB::select($query, [$name]);
        if(empty($res)) return null;

        $this->id = (int)$res[0]->id;
        $this->type = (string)$res[0]->type;
        $this->tag = (string)$res[0]->tag;
        $this->count = (int)$res[0]->count;
        $this->enabled = (bool)$res[0]->enabled;

        return $this;
    }


}