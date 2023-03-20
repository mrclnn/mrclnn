<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GalleryPostModel extends Model
{
    public int $id;
    public int $categoryId;
    public int $statusId;
    public string $fileName;
    public int $width;
    public int $height;
    public bool $shown;
    public int $size;
    // TODO представление даты не строкой
    public string $createdAt;
    public string $estimateAt;
    public string $uploadedAt;
    public string $originUri;
    // TODO что за пост айди нужно более семантически обозначить что это
    public int $postId;
    // TODO возможно не стоит добавлять это поле
    public string $debug;
    public string $hash;
    public array $tags;

    public function getById(int $id) : ?GalleryPostModel
    {

        $query = <<<QUERY
select
    id,
    category_id,
    status,
    file_name,
    width,
    height,
    shown,
    size,
    created_at,
    original_uri,
    post_id,
    estimate_at,
    hash,
    tags
from posts
where id = ?
QUERY;
        $postFromDB = DB::select($query, [$id]);

        if(empty($postFromDB)) return null;

        $this->id = (int)$postFromDB[0]->id;
        $this->categoryId = (int)$postFromDB[0]->category_id;
        $this->statusId = (int)$postFromDB[0]->status;
        $this->fileName = (string)$postFromDB[0]->file_name;
        $this->width = (int)$postFromDB[0]->width;
        $this->height = (int)$postFromDB[0]->height;
        $this->shown = (bool)$postFromDB[0]->shown;
        $this->size = (int)$postFromDB[0]->size;
        //todo запись в виде даты сделать здесь
        $this->createdAt = (string)$postFromDB[0]->created_at;
        $this->estimateAt = (string)$postFromDB[0]->estimate_at;
        $this->originUri = (string)$postFromDB[0]->original_uri;
        $this->postId = (int)$postFromDB[0]->post_id;
        $this->hash = (string)$postFromDB[0]->hash;
        $this->tags = explode(',', (string)$postFromDB[0]->tags);

        return $this;

    }

    public function setTags(array $tags) : bool
        //todo по идее можно брать список уже записанных сюда тегов, разбирать на массив, сравнивать два массива и добавлять недостающие.
        //т.е. не перезаписывать список тегов, а дополнять если какого-то нет
    {
        $tagsList = implode(',', $tags);
        $query = <<<QUERY
update
posts
set tags = ?
where id = ?
QUERY;
        //todo нужно запилить проверку заполнен ли объект, что-то типа isValid потому что если не заполнен id то тут будет пиздец
        DB::select($query, [$tagsList, $this->id]);
        //todo тут должно возвращаться не только true доработать этот момент
        return true;

    }


    // соответствие полей-свойств согласно таблице в бд
    // удалить пост
    // добавить пост в бд
    // оценить пост и тд
}
