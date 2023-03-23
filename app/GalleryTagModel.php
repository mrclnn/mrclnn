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

    public function fillFromData(object $data) : GalleryTagModel
    {
        // подразумевается что object $data это полученное из базы данных представление в Aggregator
        $this->id = $data->id ?? null;
        $this->type = $data->type ?? null;
        $this->tag = $data->tag ?? null;
        $this->count = $data->count ?? null;
        $this->enabled = $data->enabled ?? null;

        return $this;
    }

}