<?php

namespace App;

use App\Services\ImageHash;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\DB;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlNode;

class GalleryImage
{
    private string $remoteURI;
    private array $tags;
    private string $fileName;
    private string $localURI;
    private int $width;
    private int $height;
    private int $size;
    public function fillFromHtmlDocument(array $document): ?GalleryImage
    {
        if(isset($document['img']) and !empty($document['img']) and $document['img'][0] instanceof HtmlNode){
            $img = $document['img'][0];
            $imgURI = $img->attr['src'] ?? $img->attr['data-cfsrc'] ?? null;
            if(!$imgURI) return null;
            $this->remoteURI = $imgURI;
            $fileName = preg_replace('/\?\d+/', '',substr($imgURI, strripos($imgURI, '/') + 1));
            $this->fileName = "1/$fileName";
            $this->localURI = public_path() . "/img/$this->fileName";
        } else {
            return null;
        }
        if(isset($document['tags']) and !empty($document['tags']) and $document['tags'][0] instanceof  HtmlNode){
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
        } else {
            return null;
        }
        return $this;
    }

    public function isExist(): bool
    {
        return file_exists($this->localURI);
    }
    public function save(): bool
    {

        $mime = substr($this->fileName, strripos($this->fileName, '.') + 1);
        switch(true){
            case $mime === 'jpg' || $mime === 'jpeg' : $img = imagecreatefromjpeg($this->remoteURI); break;
            case $mime === 'png' : $img = imagecreatefrompng($this->remoteURI); break;
            default: return false;
        }
        if(!$img){
            throw new Exception("Unable 'create from mime' img from src $this->remoteURI");
        }
        if(imagesx($img) / imagesy($img) < 0.3 OR imagesx($img) / imagesy($img) > 2.5){
//            $this->logger->warning(sprintf('unsuitable size %s. skip. %s', imagesx($img) / imagesy($img), $src));
            throw new Exception(sprintf('unsuitable size %s. skip. %s', imagesx($img) / imagesy($img), $this->remoteURI));
        }

        $compress = $this->getCompress($img);
        $saveSuccess = imagejpeg($img, $this->localURI, $compress);
        $sizeInfo = getimagesize($this->localURI);
        $this->size = (int)filesize($this->localURI);
        $this->width = (int)$sizeInfo[0] ?: 0;
        $this->height = (int)$sizeInfo[1] ?: 0;
        //todo обработка ошибок и неудач
        return $saveSuccess;

    }
    private function getCompress($img): ?int
    {
        $limitSize = 1000000; // Для изображений больше будет применяться один и тот же максимальный уровень сжатия
        $limitQuality = 80; // Минимальный уровень качества, чем меньше тем сильнее сжатие
        $compress = floor(((imagesx($img) * imagesy($img)) / $limitSize) * 10) / 10;
        if($compress > 1) $compress = 1;
        return 100 - ((100 - $limitQuality) * $compress);
    }
    public function writeToDB(){
        $params = [
            $this->width,
            $this->height,
            $this->size,
            $this->fileName,
            $this->remoteURI,
            (new ImageHash())->createHashFromFile($this->localURI),
            $this->getTagsIds()
        ];
        $query = <<<QUERY
insert into posts
(width, height, size, category_id, file_name, original_uri, post_id, hash, tags)
values (?,?,?,0,?,?,0,?,?);
QUERY;
        DB::select($query, $params);

    }

    private function getTimeForDB() : string
    {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('Europe/Minsk'));
        return $date->format('Y.m.d H:i:s');
    }

    private function getTagsIds(): string
    {
        return implode(',', GalleryTagAggregator::checkTags($this->tags));
    }
}