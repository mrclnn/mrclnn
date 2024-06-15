<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ParserConfig
{
    // должен создать и содержать в себе пустой ParserPage
    // должен взять все данные из базы

    private array $uriTemplate;
    private string $domain;

    public string $uri;
    public function __construct(string $point, array $uriParams)
    {
        $configData = DB::table('parser')
            ->where('point', '=', $point)
            ->get();
        if($configData->count() === 0) throw new InvalidArgumentException("Not found config for point $point.");
        $configData = $configData->get(0);
//        dd($configData);
        $this->uriTemplate = json_decode($configData->get_keys, true);
        $this->domain = $configData->domain;
        $this->fillUri($uriParams);

        $parserPage = new ParserPage();
        $parserPage->setUri($this->uri);
        // получить список селекторов которые нужно найти на этой странице
        foreach(json_decode($configData->needle_selector) as $nodeName => $selector){
            $parserPage->addNode((new ParserNode())->setName($nodeName)->setSelector($selector));
        }
//        foreach(json_decode($configData->needle_attribute) as $nodeName => $attributes);


    }

    private function fillUri(array $params){

//        $requiredParams = [];
//        foreach($this->uriTemplate as $key => $value){
//            preg_match_all('/&[a-z:]+&/', $value, $expected);
//            if(empty($expected[0])) continue;
//            foreach($expected[0] as $param){
////                dd($param);
//                $param = str_replace('&', '', $param);
//                $param = explode(':', $param);
//                $requiredParams[$param[0]] = $param[1];
//            }
//        }
//
//        // param quantity check
//        $missedParams = array_diff_key($requiredParams, $params);
//        if(!empty($missedParams)){
//            $missing = implode(', ', array_keys($missedParams));
//            throw new InvalidArgumentException("Received wrong number of uri params, missing: $missing");
//        }
//
//        // param types check
//        foreach($params as $key => $value){
//            if(gettype($value) !== $requiredParams[$key]){
//                $requiredType = $requiredParams[$key];
//                $receivedType = gettype($value);
//                throw new InvalidArgumentException("Parameter $key need to be $requiredType, $receivedType received.");
//            }
//        }
//
//        $filledParams = $this->uriTemplate;
//
//        foreach($this->uriTemplate as $keyParam => $value){
//            foreach($requiredParams as $key => $type){
//                $template = $filledParams[$keyParam];
//                $filledParams[$keyParam] = str_replace("&$key:$type&", $params[$key], $template);
//            }
//        }
//
//        $this->uri = "$this->domain?" . urldecode(http_build_query($filledParams));
    }

    public function getParserPage(){
        $parserPage = new ParserPage();

    }
}