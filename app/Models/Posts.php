<?php

namespace App\Models;

use App\Helper;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class Posts extends Model
{
    public const POST_STATUS_DISABLED = 0;
    public const POST_STATUS_NORMAL = 1;
    public const POST_STATUS_FAVORITE = 2;
    public const POST_STATUS_NOT_VERIFIED = 3;
    public const POST_STATUS_UNLINKED = 4;
    public const POST_STATUS_DUPLICATE = 9;
    public $timestamps = null;
    private Collection $tagsCollection;
    private array $tagsList;


    public static function addCategoryID(Categories $category)
    {
        return self::getPostsBuilderFilteredByTags($category)
            ->whereRaw('concat(",", category_id, ",") not like concat("%,", ?, ",%")', [$category->id])
            ->update([
                'category_id' => DB::raw("trim(both ',' from concat(category_id, ',', $category->id))")
            ]);
    }

    public static function removeCategoryID(Categories $category): int
    {
        return self::query()
            ->whereRaw('concat(",", category_id, ",") like concat("%,", ?, ",%")', [$category->id])
            ->update([
                'category_id' => DB::raw(
                    "trim(both ',' from replace(concat(',', category_id, ','), concat(',', $category->id, ','), ','))"
                )
            ]);
    }

    public static function getPostsBuilderFilteredByTags(Categories $category): Builder
    {
        $postsBuilder = self::query();

        if (($extendTags = $category->getExtendTags())->isNotEmpty()) {
            $extendTagsIdList = Helper::getFieldFromModel($extendTags, 'id');
            $postsBuilder->whereHas('tags', function (Builder $tagsFilter) use ($extendTagsIdList) {
                $tagsFilter->whereIn('id', $extendTagsIdList);
            });
        }

        if (($excludeTags = $category->getExcludeTags())->isNotEmpty()) {
            $excludeTagsIdList = Helper::getFieldFromModel($excludeTags, 'id');
            $postsBuilder->whereDoesntHave('tags', function (Builder $tagsFilter) use ($excludeTagsIdList) {
                $tagsFilter->whereIn('id', $excludeTagsIdList);
            });
        }

        if (($includeTags = $category->getIncludeTags())->isNotEmpty()) {
            $includeTagsIdList = Helper::getFieldFromModel($includeTags, 'id');
            foreach($includeTagsIdList as $includeTagId){
                $postsBuilder->whereHas('tags', function (Builder $tagsFilter) use ($includeTagId) {
                    $tagsFilter->where('id', '=', $includeTagId);
                });
            }
        }

        if (($requestedStatus = $category->getRequiredStatus())->isNotEmpty()) {
            $postsBuilder->whereIn('status', $requestedStatus);
        } else if (($exceptionStatus = $category->getExceptionStatus())->isNotEmpty()) {
            $postsBuilder->whereNotIn('status', $exceptionStatus);
        }

        return $postsBuilder;
    }

    public static function getPostsBuilderFilteredByCategory(Categories $category): Builder
    {


        return self::query()->with('tags')->whereRaw('concat(",", category_id, ",") like concat("%,",?,",%")', [$category->id]);
    }

    public static function checkExistence(array $idList): array
    {
        if (empty($idList)) return [];
        $existedPostID = Posts::query()->whereIn('id', $idList)->get(['id']);
        return $existedPostID->map(function ($post) {
            return $post->id;
        })->toArray();

    }

    public static function setDuplicates(array $idList): int
    {
        if (empty($idList)) return 0;
        return Posts::query()->whereIn('id', $idList)->update(['status' => self::POST_STATUS_DUPLICATE]);

    }

    public static function setShowed(array $idList): int
    {
        //todo написать сообщение об ошибке
        if (!Helper::isIntListArray($idList)) throw new InvalidArgumentException("Invalid argument idList");
        return self::query()->whereIn('id', $idList)->update(['shown' => 1]);
    }

    public static function getFromID(int $id): ?self
    {
        $post = self::query()->find($id);
        if ($post instanceof self) return $post;
        return null;
    }


    public function tags()
    {
        return $this->belongsToMany(Tags::class);
    }

    public function getTagsCollection(): Collection
    {
        if (!isset($this->tagsCollection)) $this->setTags($this->tags ?? null);
        return $this->tagsCollection;
    }

    //todo не очень хорошо что дохера методов для хранения тэгов
    public function getTagsList(): array
    {
        if (isset($this->tagsList)) return $this->tagsList;
        $this->setTagsList();
        return $this->tagsList;
    }

    private function setTagsList()
    {
        $this->tagsList = [];
        foreach ($this->tags as $tag) {
            if (!isset($this->tagsList[$tag->type])) $this->tagsList[$tag->type] = [];
            $this->tagsList[$tag->type][] = $tag->tag;
        }
    }

    public function setTags($tags): self
    {
//        if (isset($this->tagsCollection) && $tags === $this->tags) return $this;
//        $this->tagsCollection = Tags::getTagsAsModelsList($tags);
//        $this->tags = Tags::getTagsAsIdList($this->tagsCollection);
        return $this;
    }

    public function estimate()
    {
        //todo нужна проверка не подверглись ли изменениям другие поля
        $this->status = self::POST_STATUS_FAVORITE;
        $this->save();
    }

    public function disable()
    {
        //todo нужна проверка не подверглись ли изменениям другие поля
        $this->status = self::POST_STATUS_DISABLED;
        $this->save();
    }

    public function __get($key)
    {
        switch ($key) {
//            case 'tagsCollection':
//                return $this->getTagsCollection();
//            case 'requiredStatus':
//                return $this->getRequestedStatus();
//            case 'exceptionStatus':
//                return $this->getExceptionStatus();
//            case 'extendTags':
//                return $this->getExtendTags();
//            case 'excludeTags':
//                return $this->getExcludeTags();
//            case 'includeTags':
//                return $this->getIncludeTags();
            default:
                return parent::__get($key); // TODO: Change the autogenerated stub
        }
    }

    public function __set($key, $value)
    {
        switch ($key) {
//            case 'tagsCollection':
//                $this->setTags($value);
//                break;
            default:
                parent::__set($key, $value);
        }
    }
}
