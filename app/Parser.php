<?php

namespace App;

use Exception;
use InvalidArgumentException;
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

    const STATUS_LOADED = 'loaded';
    const STATUS_CLEARED = 'cleared';
    const STATUS_BUSY = 'busy';


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
        $url = $this->getURL($params);
        $html = $this->safe_parse($url);
        $result = [];

        if($html){
            $page = (new HtmlDocument())->load($html);

            if(empty($this->needleConfig)){
                $result['page'] = $page;
            } else {
                //todo это пиздец
                foreach($this->needleConfig as $point => $config){
                    //todo обработку ошибок сюда
                    $selectors = $page->find($config['selector']);
                    $attributes = null;
                    $needleParts = null;
                    if(!empty($config['attribute'])){
                        $attributes = array_map(function($selector) use ($config){
                            $attrs = [];
                            foreach($config['attribute'] as $i => $attribute){
                                $key = null;
                                $value = null;
                                $attributeNameList = explode('|', $attribute);
                                foreach ($attributeNameList as $attributeName){
                                    if($attributeName === 'innerText'){
                                        $key = 'innerText';
                                        $value = $selector->plaintext;
                                    } else {
                                        if(!empty($selector->attr[$attributeName])){
                                            $key = $attributeName;
                                            $value = $selector->attr[$attributeName];
                                        }
                                    }
                                }
                                if(!empty($config['regex']) && !empty($value)){
                                    $regex = $config['regex'][$i];
                                    $needle = null;
                                    if(strpos($regex, '!') === 0){
                                        $regex = substr($regex, 1);
                                        $needle = preg_replace($regex, '', $value);
                                    } else {
                                        preg_match($regex, $value, $needle);
                                    }
                                }
                                $attrs[$key] = $needle ?? $value;
                            }
                            return $attrs;
                        }, $selectors);
                    }
                    $result[$point] = $attributes ?? $selectors;
                }
            }
        }

        return $result;

    }

    private function getUrL(array $params): string
    {
        $requiredParams = [];
        foreach($this->uriTemplate as $key => $value){
            preg_match_all('/&[a-zA-Z:]+&/', $value, $expected);
            if(empty($expected[0])) continue;
            foreach($expected[0] as $param){
//                dd($param);
                $param = str_replace('&', '', $param);
                $param = explode(':', $param);
                $requiredParams[$param[0]] = $param[1];
            }
        }

        // param quantity check
        $missedParams = array_diff_key($requiredParams, $params);
        if(!empty($missedParams)){
            $missing = implode(', ', array_keys($missedParams));
            throw new InvalidArgumentException("Received wrong number of uri params, missing: $missing");
        }

        // param types check
        foreach($params as $key => $value){
            if(gettype($value) !== $requiredParams[$key]){
                $requiredType = $requiredParams[$key];
                $receivedType = gettype($value);
                throw new InvalidArgumentException("Parameter $key need to be $requiredType, $receivedType received.");
            }
        }

        $filledParams = $this->uriTemplate;

        foreach($this->uriTemplate as $keyParam => $value){
            foreach($requiredParams as $key => $type){
                $template = $filledParams[$keyParam];
                $filledParams[$keyParam] = str_replace("&$key:$type&", $params[$key], $template);
            }
        }

        return "$this->domain?" . urldecode(http_build_query($filledParams));
    }

    private function getParamsTemplate(string $paramsJSON): array
    {
        //todo обработку ошибок
        return json_decode($paramsJSON, true);
    }

    private function safe_parse(string $url) : string
    {





//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
////        curl_setopt($ch, CURLOPT_)
////        curl_setopt($ch, CURLOPT_POST, 1);
////        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataAuth);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
//        $res = curl_exec($ch);
//        if(curl_error($ch)) exit('curl error: '. curl_error($ch));
//        curl_close($ch);
//
//        file_put_contents(storage_path('answer2'), $res);
//        echo(gettype($res));
//        exit;

        $h = [
//            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
//            'Accept-Encoding: gzip, deflate, br',
//            'Accept-Language: ru',
//            'Cache-Control: no-cache',
//            'Pragma: no-cache',
//            'Cookie: webmad_tl=1686386800; __cf_bm=Pt4KRzFX168O.xjRJBZTmvAbDDff9JljDBUL4IbGi74-1686387253-0-AT/YoXhDLZPGYxax0veA8JpRiPLgK6aGS3KX7Mk5efyI4VigxmflPUFdjtG74yplhg==',
//            'Connection: close',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36',
        ];

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => $h,
//                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'method' => 'GET',
                'header' => $h,
//                'ignore_errors' => true,
            ),
        ));


        try{
            return file_get_contents($url, false, $context);
        } catch (\Throwable $e){
            return false;
        }

//        file_put_contents(storage_path('answer'), $res);
//        echo "here:\n";
//        echo "url: $url\n\n";
//        var_dump($res);
//        echo(json_encode($res));
//        die;


//        $context = stream_context_create(
//            array(
////                "http" => array(
//////                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36",
////                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36"
////                ),
//                "ssl"=>array(
//                    "verify_peer"=>false,
//                    "verify_peer_name"=>false,
//                ),
//            )
//        );
//        return file_get_contents($url, false, $context);
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