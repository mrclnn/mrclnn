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
    // проверяет наличие тэга в базе данных, основываясь на имеющихся заполненных полях объекта
    // заполняет объект данными из базы если тэг найден, инсертит тэг в базу если нет
    public function fillOrInsert(): GalleryTagModel
    {
        $tags = DB::table('tags')
            ->where('tag', '=', $this->tag)
            ->where('type', '=', $this->type)
            ->get();
        //todo будем считать что type+tag это уникальная пара и база не содержит дубликатов по этому ключу
        if($tags->count() === 1){
            $tag = $tags->get(0);
            $this->id = $tag->id;
            $this->count = $tag->count;
            $this->enabled = $tag->enabled;
            return $this;
        }
        $this->insert();
        return $this;

    }
    public function setName(string $name): GalleryTagModel
    {
        //todo не должен содержать пробелов, необходимо заменять все пробелы на нижние подчеркивания
        // впрочем скорее всего эта деталь уже не необходимо нужно проверить
        $this->tag = str_replace(' ', '_', $name);;
        return $this;
    }
    public function setType(string $type): GalleryTagModel
    {
        $this->type = $type;
        return $this;
    }
    private function collectData(): array
    {
        $data = [];
        if(isset($this->tag)) $data['tag'] = $this->tag;
        if(isset($this->type)) $data['type'] = $this->type;
        if(isset($this->count)) $data['count'] = $this->count;
        if(isset($this->enabled)) $data['enabled'] = $this->enabled;
        return $data;
    }
    public function insert(): bool
    {
        $id = DB::table('tags')
            ->insertGetId($this->collectData());
        //todo обработку
        $this->id = $id ?: 0;
        return !!$id;
    }

}