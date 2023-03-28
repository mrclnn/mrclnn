<?php

namespace App;

use Illuminate\Support\Facades\DB;

class ParserAggregator
{
    public static function getParser(string $point): ?Parser
    {
        $query = <<<QUERY
select * from parser where point = ?
QUERY;
        $parserData = DB::select($query, [$point]);
        if (empty($parserData)) return null;
        return (new Parser())->fillFromData($parserData[0]);

    }
}