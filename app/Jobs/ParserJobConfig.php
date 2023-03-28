<?php

namespace App\Jobs;

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

}