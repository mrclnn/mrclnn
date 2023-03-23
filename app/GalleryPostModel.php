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
    public array $tagsIds;

    public array $tags = [];



    public function getTags() : array
    {
        if(!empty($this->tags)) return $this->tags;
        if(empty($this->tagsIds)) return [];
        $this->tags = GalleryTagAggregator::getFromIDs($this->tagsIds);
        return $this->tags;
    }

    public function fillByDBData(object $data): GalleryPostModel
    {

        $this->id = isset($data->id) ? (int)$data->id : null;
        $this->categoryId = isset($data->category_id) ? (int)$data->category_id : null;
        $this->statusId = isset($data->status) ? (int)$data->status : 0;
        $this->fileName = isset($data->file_name) ? (string)$data->file_name : null;
        $this->width = isset($data->width) ? (int)$data->width : null;
        $this->height = isset($data->height) ? (int)$data->height : null;
        $this->shown = isset($data->shown) ? (bool)$data->shown : null;
        $this->size = isset($data->size) ? (int)$data->size : null;
        //todo запись в виде даты сделать здесь
        $this->createdAt = isset($data->created_at) ? (string)$data->created_at : null;
        $this->estimateAt = isset($data->estimate_at) ? (string)$data->estimate_at : '';
        $this->originUri = isset($data->original_uri) ? (string)$data->original_uri : null;
        $this->postId = isset($data->post_id) ? (int)$data->post_id : null;
        $this->hash = isset($data->hash) ? (string)$data->hash : null;
        $this->tagsIds = isset($data->tags) ? explode(',', (string)$data->tags) : [];
        $this->tags = $this->getTags();

        return $this;
    }

//    public function setTags(array $tags) : bool
//    {
//        //todo по идее можно брать список уже записанных сюда тегов, разбирать на массив, сравнивать два массива и добавлять недостающие.
//        //т.е. не перезаписывать список тегов, а дополнять если какого-то нет
//        $tagsList = implode(',', $tags);
//        $query = <<<QUERY
//update
//posts
//set tags = ?
//where id = ?
//QUERY;
//        //todo нужно запилить проверку заполнен ли объект, что-то типа isValid потому что если не заполнен id то тут будет пиздец
//        DB::select($query, [$tagsList, $this->id]);
//        //todo тут должно возвращаться не только true доработать этот момент
//        return true;
//
//    }


    // соответствие полей-свойств согласно таблице в бд
    // удалить пост
    // добавить пост в бд
    // оценить пост и тд
}
