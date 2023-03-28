<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;

class ParserJobConfig
{
    // todo тут должна быть связь с таблицей

    private array $parserList;
    public int $delay;
    public function __construct(string $name)
    {
//        $query = <<<QUERY
//select * from parser_job_config where name = ?
//QUERY;
//        $configData = DB::select($query, [$name]);
//        $this->parserList = explode(',', (string)($configData[0]->parser_list));

    }
}