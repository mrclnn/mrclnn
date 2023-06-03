<?php

namespace App\Jobs;


use App\GalleryTagAggregator;
use App\Models\Posts;
use Illuminate\Support\Facades\DB;

class ParserJobConfig
{
    // todo тут должна быть связь с таблицей

    private string $name;
    private array $parserList;
    public int $delay;
    public string $category;
    public int $pid = 0;
    public int $iteration = 0;
    public array $needleIds = [];

    public int $lastPage;
    public string $filter;

    public bool $isItLastIteration = true;

    private array $nextPageCondition;
    public function __construct(string $name, string $category)
    {
        $this->category = $category;
        $query = <<<QUERY
select * from parser_job_config where name = ?
QUERY;
        $configData = DB::select($query, [$name]);
        $this->parserList = explode(',', (string)($configData[0]->parser_list));
        $this->nextPageCondition = json_decode($configData[0]->next_page_condition, true);
        $this->delay = (int)$configData[0]->delay;
        $disabledTags = GalleryTagAggregator::getDisabledTags();
        $filter = array_map(function($tag){return $tag->tag;}, $disabledTags);
        $this->filter = '+-' . implode('+-', $filter);

    }

    public function prepareNextIteration(){
        $this->pid = $this->pid + 42;
        $this->iteration++;
        $this->isItLastIteration = !($this->iteration + 1 <= $this->lastPage);
    }

    public function processPagination(array $pagination)
    {
        if(isset($this->lastPage)) return;
        $this->lastPage = empty($pagination) ? 1 : ((int)$pagination[0]['href'][0] / 42) + 1;
    }
    public function processContent(array $content)
    {
        $idList = array_map(function($post){return $post['href'][0];}, $content);
        $existedIds = Posts::checkExistence($idList);
        $this->needleIds = array_diff($idList, $existedIds);
    }

}