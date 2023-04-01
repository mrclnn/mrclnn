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

        if(
            !(isset($document['img']) and !empty($document['img']) and $document['img'][0] instanceof HtmlNode) or
            !(isset($document['tags']) and !empty($document['tags']) and $document['tags'][0] instanceof  HtmlNode)
        ) return null;

        $img = $document['img'][0];
        $imgURI = $img->attr['src'] ?? $img->attr['data-cfsrc'] ?? null;
        if(!$imgURI) return null;
        $this->remoteURI = $imgURI;
        preg_match('/\d+$/', $this->remoteURI, $postID);
        $this->postId = $postID[0];
        $fileName = preg_replace('/\?\d+/', '',substr($imgURI, strripos($imgURI, '/') + 1));
        $this->fileName = "1/$fileName";
        $this->localURI = public_path() . "/img/$this->fileName";

        $tagsList = $document['tags'][0];
        $tags = $tagsList->find('li.tag');
        if(empty($tags)) return $this;
        foreach($tags as $tag){
            $class = trim($tag->attr['class']);
            if(preg_match('/^tag-type-[a-z]+ tag$/', $class) !== 1) return $this;
            $type = str_replace(['tag-type-', ' tag'], '', $class);
            $value = preg_replace(['/(^\? )|( [0-9]+$)/', '/\s/'], ['', '_'], $tag->plaintext);
            $this->tags[$type][] = $value;
        }

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
            'size' => $this->size,
            'category_id' => 0,
            'file_name' => $this->fileName,
            'original_uri' => $this->remoteURI,
            'post_id' => $this->postId,
            'hash' => (new ImageHash())->createHashFromFile($this->localURI),
            'tags' => $this->getTagsIds()
        ];
        return DB::table('posts')
            ->insert($params);

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
        return implode(',', GalleryTagAggregator::checkTags($this->tags));
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