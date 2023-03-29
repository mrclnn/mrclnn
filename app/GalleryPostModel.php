<?php

namespace App;

use DateTime;
use DateTimeZone;
use Exception;
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

    public function estimate(int $estimate): bool
    {
        if($this->statusId === $estimate) return true;
        DB::select('update posts set status = ?, estimate_at = ? where id = ?', [$estimate, $this->getTimeForDB(), $this->id]);
        //todo обработку ошибок
        return true;
    }

    private function getTimeForDB() : string
    {
        //todo вынести этот метод куда-нибудь выше по абстракции
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('Europe/Minsk'));
        return $date->format('Y.m.d H:i:s');
    }

}
