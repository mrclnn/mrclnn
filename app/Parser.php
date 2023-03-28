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
    private HtmlWeb $doc;
    private Logger $logger;

    private int $id;
    private string $point;
    private string $domain;
    private array $uriTemplate;
    private array $needleSelectors;
//    private array $nextPageCondition;


    public function __construct()
    {
        $this->doc = new HtmlWeb();
        $this->setLogger();
    }


    public function fillFromData(object $data): Parser
    {
        $this->id = isset($data->id) ? (int)$data->id : 0;
        $this->point = isset($data->point) ? (string)$data->point : '';
        $this->domain = isset($data->domain) ? (string)$data->domain : '';
        $this->uriTemplate = isset($data->get_keys) ? $this->getParamsTemplate((string)$data->get_keys) : [];
        $this->needleSelectors = isset($data->needle_selector) ? json_decode((string)$data->needle_selector, true) : [];
//        $this->nextPageCondition = isset($data->next_page_condition) ? json_decode((string)$data->next_page_condition, true) : [];

        return $this;

    }

    public function parse(array $params): array
    {
        //todo нужно проверять передаваемые параметры
        $url = $this->getURL($this->getParams($params));
        $page = (new HtmlDocument())->load($this->safe_parse($url));
//        $page = $this->doc->load($url);
        $result = [];
        if(empty($this->needleSelectors)){
            $result['page'] = $page;
        } else {
            foreach($this->needleSelectors as $point => $selector){
                //todo проверка
//                echo "selector : $selector<br>point : $point<br><br><br>";
                $result[$point] = $page->find($selector);
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