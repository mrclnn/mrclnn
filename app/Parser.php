<?php

namespace App;

use Exception;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlWeb;

class Parser
{

    private int $id;
    private string $point;
    private string $domain;
    private array $uriTemplate;
    private array $needleConfig = [];


    public function __construct()
    {
        $this->setLogger();
    }


    public function fillFromData(object $data): ?Parser
    {
        //property_exists используется потому что null значения допускаются, а значит isset или empty не подходят
        $this->id = property_exists($data, 'id') ? (int)$data->id : 0;
        $this->point = property_exists($data, 'point') ? (string)$data->point : '';
        $this->domain = property_exists($data, 'domain') ? (string)$data->domain : '';
        $this->uriTemplate = property_exists($data, 'get_keys') ? $this->getParamsTemplate((string)$data->get_keys) : [];

        if(
            property_exists($data, 'needle_selector') &&
            property_exists($data, 'needle_attribute') &&
            property_exists($data, 'needle_regex')
        ){
            //todo проверка на невозможность получить json;
            $selectors = json_decode((string)$data->needle_selector, true) ?? [];
            $attributes = json_decode((string)$data->needle_attribute, true) ?? [];
            $regex = json_decode((string)$data->needle_regex, true) ?? [];
            $this->setConfig($selectors, $attributes, $regex);
        } else {
            //todo нужно избавиться от возвращения null
            return null;
        }

        return $this;

    }

    private function setConfig(array $selectors, array $attributes, array $regex): void
    {
        foreach($selectors as $point => $selector){
            $this->needleConfig[$point]['selector'] = $selector;
            $this->needleConfig[$point]['attribute'] = $attributes[$point] ?? null;
            $this->needleConfig[$point]['regex'] = $regex[$point] ?? null;
        }
    }

    public function parse(array $params): array
    {
        //todo нужно проверять передаваемые параметры
        $url = $this->getURL($this->getParams($params));
        $page = (new HtmlDocument())->load($this->safe_parse($url));
        $result = [];
        if(empty($this->needleConfig)){
            $result['page'] = $page;
        } else {
            foreach($this->needleConfig as $point => $config){
                //todo обработку ошибок сюда
                $selectors = $page->find($config['selector']);
                if(!empty($config['attribute'])){
                    $attributes = array_map(function($selector) use ($config){
                        return $selector->attr[$config['attribute']];
                    }, $selectors);
                    if(!empty($config['regex'])){
                        $needleParts = array_map(function($attribute) use ($config){
                            preg_match($config['regex'], $attribute, $needle);
                            return $needle[0] ?? null;
                        }, $attributes);
                    }
                }
                $result[$point] = $needleParts ?? $attributes ?? $selectors;
            }
        }
        return $result;

    }

    private function getURL(array $params): string
    {
        $params = http_build_query($params);
        $params = urldecode($params);
        return "$this->domain?$params";
    }

    private function getParams(array $params): array
    {
        //тут нужно проверить
        $index = 0;
        $verifiedParams = [];
        foreach($this->uriTemplate as $key => $value){
            //todo обработка ошибок
            preg_match('/&[a-z]+&/', $value, $type);
            if(!empty($type)){
                $type = str_replace('&', '', $type[0]);
                if(gettype($params[$index]) === $type){
                    $value = str_replace("&$type&", $params[$index], $value);;
                    $index++;
                } else {
                    throw new Exception("Received query param are mistyped with template");
                }
            }
            $verifiedParams[$key] = $value;

        }
        return $verifiedParams;
    }

    private function getParamsTemplate(string $paramsJSON): array
    {
        //todo обработку ошибок
        return json_decode($paramsJSON, true);
    }

    private function safe_parse(string $url) : string
    {
        $context = stream_context_create(
            array(
                "http" => array(
//                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36",
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36"
                )
            )
        );
        return file_get_contents($url, false, $context);
    }

    private function setLogger() : void
    {
        $this->logger = new Logger(
            'queries', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/queries.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }

}