<?php

namespace App;

use App\Services\ImageHash;
use Exception;
use Illuminate\Support\Facades\DB;
use simplehtmldom\HtmlNode;
use Throwable;

class GalleryImage
{
    private string $remoteURI;
    private array $tags;
    private string $fileName;
    private string $localURI;
    private int $width;
    private int $height;
    private int $size;
    private int $postId;
    public function fillFromHtmlDocument(array $document): ?GalleryImage
    {

        if( empty($document['img']) || empty($document['tags'])) return null;
        $this->remoteURI = $document['img'][0]['src'];
        preg_match('/\d+$/', $this->remoteURI, $postID);
        $this->postId = $postID[0];
        $fileName = preg_replace('/\?\d+/', '',substr($this->remoteURI, strripos($this->remoteURI, '/') + 1));
        $this->fileName = $fileName;
        $this->localURI = storage_path("gallery/posts/$this->fileName");

        $this->tags = $document['tags'];

        return $this;
    }

    public function isExist(): bool
    {
        return file_exists($this->localURI);
    }
    public function save(): GalleryImage
    {

        $mime = substr($this->fileName, strripos($this->fileName, '.') + 1);
        $successfullySaved = false;
        //todo по непонятным причинам в некоторых случаях возникает ошибка
        // imagecreatefrompng(): gd-png: libpng warning: iCCP: known incorrect sRGB profile
        // которая исчезает если попытаться прогнать этот же код еще раз
        $failedCount = 0;
        $failedLimit = 5;
        do{
            try{
                switch(true){
                    case $mime === 'jpg' || $mime === 'jpeg' : $img = imagecreatefromjpeg($this->remoteURI); break;
                    case $mime === 'png' : $img = imagecreatefrompng($this->remoteURI); break;
                    default: return $this;
                }
                $successfullySaved = true;
            } catch (Throwable $e){
                $failedCount++;
                if($failedCount > $failedLimit) throw $e;
                sleep(1);
            }
        } while (!$successfullySaved);

        if(!$img){
            throw new Exception("Unable 'create from mime' img from src $this->remoteURI");
        }
        if(imagesx($img) / imagesy($img) < 0.3 OR imagesx($img) / imagesy($img) > 2.5){
//            $this->logger->warning(sprintf('unsuitable size %s. skip. %s', imagesx($img) / imagesy($img), $src));
            throw new Exception(sprintf('unsuitable size %s. skip. %s', imagesx($img) / imagesy($img), $this->remoteURI));
        }

        $compress = $this->getCompress($img);
        $saveSuccess = imagejpeg($img, $this->localURI, $compress);
        if(!$saveSuccess) return $this;
        $sizeInfo = getimagesize($this->localURI);
        $this->size = (int)filesize($this->localURI);
        $this->width = (int)$sizeInfo[0] ?: 0;
        $this->height = (int)$sizeInfo[1] ?: 0;
        //todo обработка ошибок и неудач
        return $this;

    }
    public function writeToDB(): bool
    {
        if(!$this->isValid()) return false;
        $params = [
            'width' => $this->width,
            'height' => $this->height,
            'ratio' => round($this->width / $this->height, 1),
            'size' => $this->size,
            'category_id' => 0,
            'file_name' => $this->fileName,
            'original_uri' => $this->remoteURI,
            'post_id' => $this->postId,
            'hash' => (new ImageHash())->createHashFromFile($this->localURI),
        ];
        $postID = DB::table('posts')->insertGetId($params);
        $insert = [];
        foreach(explode(',', $this->getTagsIds()) as $tagID){
            $insert[] = [
                'posts_id' => $postID,
                'tags_id' => $tagID
            ];
        }
        DB::table('posts_tags')->insert($insert);
        //todo тут по хорошему все переписать вообще
        return true;

    }

    private function getCompress($img): ?int
    {
        $limitSize = 1000000; // Для изображений больше будет применяться один и тот же максимальный уровень сжатия
        $limitQuality = 80; // Минимальный уровень качества, чем меньше тем сильнее сжатие
        $compress = floor(((imagesx($img) * imagesy($img)) / $limitSize) * 10) / 10;
        if($compress > 1) $compress = 1;
        return 100 - ((100 - $limitQuality) * $compress);
    }

    private function getTagsIds(): string
    {
        $tagsIds = [];
        foreach($this->tags as $tag){
            $tagsIds[] = (new GalleryTagModel())
                ->setName($tag['innerText'])
                ->setType($tag['class'])
                ->fillOrInsert()
                ->id;
        }
        return implode(',', $tagsIds);
    }

    private function isValid(): bool
    {
        //todo возможно записывать причину не валидности
        //successfully received remote data
        if(empty($this->remoteURI)) return false;
        if(empty($this->postId)) return false;
        if(empty($this->tags)) return false;

        //successfully local saved
        if(empty($this->size)) return false;
        if(empty($this->width)) return false;
        if(empty($this->height)) return false;

        return true;
    }
}