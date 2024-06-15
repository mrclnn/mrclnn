<?php

namespace App\Models;

use App\GalleryTagModel;
use App\Helper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class Tags extends Model
{
    //
    public static function getFromID($id): Collection
    {
        if(gettype($id) === 'integer') $idList = [$id];
        if(gettype($id) === 'array') $idList = $id;
        if($id instanceof Collection) $idList = $id->toArray();
        //todo дописать тут сообщение об ошибке адекватное
        if(!isset($idList)) throw new InvalidArgumentException("Invalid argument $id received");

        if(empty($idList)) return collect([]);
        return self::query()->whereIn('id', $idList)->get();
    }
    public static function getFromName(string $name): ?self
    {
        $model = self::query()->where('tag', '=', $name)->first();
        if($model instanceof self) return $model;
        return null;
    }

    public static function getFromNameSearch(string $search): ?self
    {
        $model = self::query()->where('tag', 'like', "%$search%")->first();
        if($model instanceof self) return $model;
        return null;
    }
    public static function getTagsAsModelsList($tags)
    {
        if(empty($tags)) return new Collection;
        //todo нужно ли добавить сюда поведение "добавить теги" а не "перезаписать теги"
        if(gettype($tags) === 'string' && Helper::isIntListString($tags)) $tags = collect(explode(',', $tags));
        if(gettype($tags) === 'array') $tags = collect($tags);
        if($tags instanceof Collection){
            //todo не особо красивый код
            return $tags->map(function($tag){
                if(gettype($tag) === 'integer') return self::getFromID($tag)->first();
                if(gettype($tag) === 'string' && Helper::isIntListString($tag)) return self::getFromID((int)$tag)->first();
                if($tag instanceof self) return $tag;
                if($tag instanceof GalleryTagModel) return self::getFromID($tag->id)->first();
                return null;
            })->filter(function($tag){
                return !empty($tag);
            });
        }
        //todo возможно стоит как-то оповещать о том что переданные данные не удовлетворили проверкам и по этому возвращается пустая коллекция
        // дописать тут сообщение об ошибке адекватное, возможно стоит выбрасывать warning а не ошибку
        // что-то на подобие trigger_error($yourErrorMessage, E_USER_WARNING);
        throw new InvalidArgumentException("Invalid argument received");
//        return new Collection;
    }
    public static function getTagsAsIdList(Collection $tags): string
    {
        if($tags->isEmpty()) return '';
        $idList = '';
        foreach ($tags as $tag){
            //todo временная поддержка GalleryTagModel
            if($tag instanceof GalleryTagModel) $idList .= "$tag->id,";
            if($tag instanceof self) $idList .= "$tag->id,";
        }
        return substr($idList, 0, -1);
    }
    public function posts()
    {
        return $this->belongsToMany(Posts::class);
    }
}
