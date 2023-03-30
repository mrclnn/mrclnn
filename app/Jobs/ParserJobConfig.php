<?php

namespace App\Jobs;

use App\GalleryPostAggregator;
use Exception;
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

    }

    public function prepareNextIteration(){
        $this->pid = $this->pid + 42;
        $this->iteration++;
        $this->isItLastIteration = !($this->iteration <= $this->lastPage);
    }

    public function processPagination(array $pagination)
    {
        if($this->lastPage) return;
        $this->lastPage = empty($pagination) ? 1 : ((int)$pagination[0] / 42) + 1;
    }
    public function processContent(array $content)
    {
        //$content -- это массив id постов
        $idList = $content;
        $existedIds = GalleryPostAggregator::checkExistence($idList);
        $this->needleIds = array_diff($idList, $existedIds);
    }

}