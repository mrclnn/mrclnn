<?php

namespace App\Models;

use App\GalleryTagAggregator;
use App\GalleryTagModel;
use App\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

class Categories extends Model
{
    public const CATEGORY_STATUS_DISABLED = 0;
    public const CATEGORY_STATUS_ENABLED = 1;
    public const CATEGORY_STATUS_DELETED = 9;
    private static Collection $enabledCategories;

    //todo возможно в названии переменной стоит уточнить что мы получаем статус поста а не категории
    private Collection $requiredStatus;
    private Collection $exceptionStatus;
    private Collection $extendTags;
    private Collection $excludeTags;
    private Collection $includeTags;
    private Builder $postsQuery;

    public static function disableAbandonedPosts(){
        //todo это функция для отключения постов в запрещённых тегах
    }
    public static function getEnabled(): Collection
    {
        if (isset(self::$enabledCategories)) return self::$enabledCategories;
        return self::$enabledCategories = self::query()
            ->where('status', '=', self::CATEGORY_STATUS_ENABLED)
            ->orderBy('count', 'desc')
            ->get();
    }
    public static function getFromID(int $id): ?Categories
    {
        $category = self::query()->find($id);
        if($category instanceof Categories) return $category;
        return null;
    }
    public static function getFromName(string $name): ?Categories
    {
        $category = self::query()->where('name', '=', $name)->first();
        if($category instanceof Categories) return $category;
        return null;
    }
    public static function getFromTag(string $tag): ?Categories
    {
        $category = self::getFakeCategory();
        $tag = Tags::getFromName($tag);
        if(!$tag) return null;
        //todo добавить для таблицы тэгов поле name
        $category->enabled = true;
        return $category
            ->setName($tag->tag)
            ->setIncludeTags([$tag])
            ->reCount();
    }
    public static function getFakeCategory(): Categories
    {
        return (new self)->setExceptionStatus([
            Posts::POST_STATUS_DUPLICATE,
            Posts::POST_STATUS_DISABLED
        ])->setStatus(self::CATEGORY_STATUS_ENABLED);
    }


    public function setStatus(int $status): self
    {
        if(!$this->isStatusExist($status)) throw new InvalidArgumentException("Trying to set invalid status $status for category");
        $this->status = $status;
        return $this;
    }
    private function isStatusExist(int $status): bool
    {
        $found = false;
        foreach((new ReflectionClass(__CLASS__))->getConstants() as $statusName => $statusValue){
            if($statusValue === $status && strpos($statusName, 'CATEGORY_STATUS_') === 0) $found = true;
        };
        return $found;
    }
    public function reCount(): Categories
    {
        if(isset($this->id)){
            $this->count = $this->getPostsQuery()->count();
        } else {
            $this->count = Posts::getPostsBuilderFilteredByCategory($this)->count();
        }
        return $this;
    }
    public function getRequiredStatus() : Collection
    {
        if(isset($this->requiredStatus)) return $this->requiredStatus;
        $this->setRequiredStatus($this->required_status);
        return $this->requiredStatus;
    }
    public function setRequiredStatus($requiredStatus): self
    {
        if($this->required_status === $requiredStatus && isset($this->requiredStatus)) return $this;
        $this->requiredStatus = collect(self::normalizeStatus($requiredStatus));
        $this->required_status = implode(',', $this->requiredStatus->toArray());
        return $this;
    }
    public function getExceptionStatus() : Collection
    {
        if(isset($this->exceptionStatus)) return $this->exceptionStatus;
        $this->setExceptionStatus($this->exception_status);
        return $this->exceptionStatus;
    }
    public function setExceptionStatus($exceptionStatus): self
    {
        if($this->exception_status === $exceptionStatus && isset($this->exceptionStatus)) return $this;
        $this->exceptionStatus = collect(self::normalizeStatus($exceptionStatus));
        $this->exception_status = implode(',', $this->exceptionStatus->toArray());
        return $this;
    }
    public function getExtendTags(): Collection
    {
        if(!isset($this->extendTags)) $this->setExtendTags($this->extend_tags ?? null);
        return $this->extendTags;
    }
    public function setExtendTags($extendTags): Categories
    {
        //todo возможна ли тут коллизия? exclude_tags и excludeTags могут измениться только через этот метод
        if($this->extend_tags === $extendTags && isset($this->extendTags)) return $this;
        $this->extendTags = Tags::getTagsAsModelsList($extendTags);
        $this->extend_tags = Tags::getTagsAsIdList($this->extendTags);
        return $this;
    }
    public function getExcludeTags(): Collection
    {
        if(!isset($this->excludeTags)) $this->setExcludeTags($this->exclude_tags ?? null);
        return $this->excludeTags;
    }
    public function setExcludeTags($excludeTags): Categories
    {
        //todo возможна ли тут коллизия? exclude_tags и excludeTags могут измениться только через этот метод
        if($this->exclude_tags === $excludeTags && isset($this->excludeTags)) return $this;
        $this->excludeTags = Tags::getTagsAsModelsList($excludeTags);
        $this->exclude_tags = Tags::getTagsAsIdList($this->excludeTags);
        return $this;
    }
    public function getIncludeTags(): Collection
    {
        if(!isset($this->includeTags)) $this->setIncludeTags($this->include_tags ?? null);
        return $this->includeTags;
    }
    public function setIncludeTags($includeTags): Categories
    {
        //todo возможна ли тут коллизия? exclude_tags и excludeTags могут измениться только через этот метод
        if($this->include_tags === $includeTags && isset($this->includeTags)) return $this;
        $this->includeTags = Tags::getTagsAsModelsList($includeTags);
        $this->include_tags = Tags::getTagsAsIdList($this->includeTags);
        return $this;
    }
    public function setName(string $name): Categories
    {
        $this->name = $name;
        return $this;
    }
    public function setCount(int $count): Categories
    {
        $this->count = $count;
        return $this;
    }
    public function getPostsForSlider(int $quantity, float $screen): Collection
    {

        $posts = $this->getPostsQuery()
            ->where('shown', 0)
            ->select([
                'id',
                'file_name',
                'status',
                'width',
                'height'
            ])
            ->orderByRaw(
                'abs(ratio - ?), rand()',
                [$screen]
            )
            ->limit($quantity)
            ->get();

        //todo при таком подходе нет смысла в count поле модели/таблицы бд.
        // однако если не вычислять точное значение на данный момент то есть риск попасть в бесконечную рекурсию
        $this->reCount();

        if($posts->count() === $quantity || $posts->count() === $this->count) return $posts;
        // здесь окажемся только в том случае, если
        // количество полученных постов меньше чем запрашиваемое количеств И меньше чем общее кол-во постов категории

        //получить список id
        $retrievedPostsID = $posts->map(function($post){return $post->id;});
        //обновить shown
        $this->resetShown();
        //получить недостающее кол-во постов
        $quantityRestRequired = min($this->count, $quantity) - $posts->count();
        //$quantityRestRequired не должен быть больше чем общее кол-во постов в категории, иначе бесконечная рекурсия
        if($quantityRestRequired > $this->count){
            throw new RuntimeException("Required more then available posts: $quantityRestRequired/$this->count");
        }
        $restPosts = $this->getPostsForSlider($quantityRestRequired, $screen);
        if($restPosts->count() + $posts->count() === min($quantity, $this->count)){
            return $posts->merge($restPosts);
        }

        throw new RuntimeException("Unable to exit from recursion");
    }
    public function getAllPosts(): Collection
    {
        return $this->getPostsQuery()
            ->orderBy('hash')
            ->get();
    }

    public function tags()
    {
        return $this->hasMany(Tags::class, );
    }


    private function getPostsQuery() : Builder
    {
        if(isset($this->postsQuery)) return clone $this->postsQuery;

//        $query = Posts::getPostsBuilderFilteredByCategory($this);
        $query = Posts::getPostsBuilderFilteredByTags($this);

        return clone $this->postsQuery = $query;
    }
    private function resetShown(): void
    {
        $this->getPostsQuery()->where('shown', '=', 1)->update(['shown' => 0]);
    }
    private function normalizeStatus($status): array
    {
        if(is_string($status) && Helper::isIntListString($status)) $status = explode(',', $status);
        if($status instanceof Collection) $status = $status->toArray();
        return is_array($status) ? $status : [];
    }
    private function isValidString(string $string): bool
    { // true если строка содержит только цифры разделённые запятой
        return preg_match('/^[\d,]+$/', trim($string)) === 1;
    }


    public function delete()
    {
        $this->setStatus(self::CATEGORY_STATUS_DELETED);
        Posts::removeCategoryID($this);
        $this->update();
    }
    public function __get($key)
    {
        switch ($key){
            case 'requiredStatus':
                return $this->getRequiredStatus();
            case 'exceptionStatus':
                return $this->getExceptionStatus();
            case 'extendTags':
                return $this->getExtendTags();
            case 'excludeTags':
                return $this->getExcludeTags();
            case 'includeTags':
                return $this->getIncludeTags();
            default:
                return parent::__get($key);
        }
    }
    public function __set($key, $value)
    {
        switch($key){
            case 'requiredStatus':
                $this->setRequiredStatus($value);
                break;
            case 'exceptionStatus':
                $this->setExceptionStatus($value);
                break;
            case 'extend_tags':
            case 'extendTags':
                $this->setExtendTags($value);
                break;
            case 'exclude_tags':
            case 'excludeTags':
                $this->setExcludeTags($value);
                break;
            case 'include_tags':
            case 'includeTags':
                $this->setIncludeTags($value);
                break;
            default:
                parent::__set($key, $value);
        }
    }

}
