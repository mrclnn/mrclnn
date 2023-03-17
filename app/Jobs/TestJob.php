<?php

namespace App\Jobs;

use App\Services\ImageHash;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use simplehtmldom\HtmlWeb;
use Throwable;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LoggerAwareTrait;

    public $timeout = 240;

//    private $offset;
    private $limit;
    private $cat_id;
    private $filters;
    private $offset;
    private $existingLink;
    private $currentLink;
    private $doc;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $existingLink = null)
    {
        if(!$existingLink){
            $this->existingLink = [['link' => 'https://remval.by/', 'checked' => true], ['link' => 'https://remval.by', 'checked' => false]];
            file_put_contents(storage_path('logs/temporary.log'), '');
        } else {
            $this->existingLink = $existingLink;
        }

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->setLogger();
        $this->logger->info('start job...');

        $this->doc = new HtmlWeb();

        if(!$this->existingLink){
            $this->logger->emergency('empty existing link');
            return;
        }
//        $this->logger->debug('existing', $this->existingLink);
        $success = $this->getAllPageLinks();
        if($success){
            $this->logger->error('success, continue');
            self::dispatch($this->existingLink)->delay(Carbon::now()->addSeconds(1));
        } else {
            $this->logger->error('false return, end work');
        }

    }

//    private function register(array $result){
//        foreach ($result as $lead){
//            if(!in_array($lead->UF_CRM_1611743664675, $this->filters)){
//                $this->logger->info($lead->UF_CRM_1611743664675 . ' is new. write to file.');
//                $this->filters[] = $lead->UF_CRM_1611743664675;
//                file_put_contents('tmp.log', $lead->UF_CRM_1611743664675 . PHP_EOL, FILE_APPEND);
//            }
//        }
//
//    }
    private function getAllPageLinks() : bool
    {
        $url = false;

        foreach($this->existingLink as $link){
            if(!$link) continue;
            if($link['checked'] === true){
//                $checkedTrue++;
                continue;
            }
            $url = $link['link'];
            break;
        }
        if(!$url){
            $this->logger->debug('not found false link. end work');
            return false;
        }
        $this->logger->debug('checking '.$url);
//        $this->logger->debug('allCount : '.count($this->existingLink));
        $page = $this->doc->load($url);
        if(!$page){
            echo 'not found page ' . $url;
            $this->logger->error('not found page, delete from result array '.$url);
            foreach($this->existingLink as $i => $lnk){
                if(!$lnk) continue;
                if($lnk['link'] === $url) {
                    $this->existingLink[$i]['checked'] = true;
                }
            }
            return true;
        }
        foreach($page->find('a') as $link){
//            $newLinks = true;
            foreach($this->existingLink as $existedLink){
                if(!$existedLink) continue;
                if($existedLink['link'] === $link->href) continue 2;
            }
            if(strpos($link->href, '://remval.by/') === false) continue;
            if(preg_match('/\.[a-z]+$/', $link->href)) continue;
            $this->existingLink[] = ['link' => $link->href, 'checked' => false];
            $this->logger->debug('added new link');
        }
        $title = $page->find('title', 0);
        $title = ($title && $title->innertext) ? $title->innertext : 'empty';
        $desc = $page->find('meta[name=description]', 0);
        $desc = ($desc && $desc->content) ? $desc->content : 'empty';
        $checkedTrue = 0;
        $all = count($this->existingLink);
        foreach($this->existingLink as $i => $lnk){
            if(!$lnk) continue;
            if($lnk['link'] === $url) {
//                $this->logger->debug('debug', $this->existingLink);
                $this->existingLink[$i]['checked'] = true;
                $this->logger->debug('mark as checked');
                $this->writeData([$url, $title, $desc]);
            }
            if($lnk['checked'] === true){
                $checkedTrue++;
            }
        }

        $this->logger->debug("checked $checkedTrue/$all");
        return true;
    }

    private function writeData(array $data){
        $string = implode('|;|', $data);
        file_put_contents(storage_path('logs/temporary.log'), $string.PHP_EOL, FILE_APPEND);
    }

//    private function testBX(int $start = 0, array $filter = [])
//    {
//        $method = 'crm.lead.list';
////        $method = 'crm.contact.delete';
//        $uri = 'https://b24-il9uen.bitrix24.by/rest/1/pygj6lau7vixoam1/'.$method.'.json';
////        $uri = 'https://okko.bitrix24.by/rest/23/bspwvqmlrdmmzqcr/crm.lead.userfield.list.json';
//
//        $queryArray = [
//            'start' => $start,
//            'order' => [
//                'DATE_CREATE' => 'DESC'
//            ],
//            'select' => ['UF_CRM_1611743664675'],
//            'filter' => [
//                '!UF_CRM_1611743664675' => $filter,
//
//            ]
//        ];
//        $client = new Client();
//        $queryString = preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($queryArray));
//        try{
//            $response = $client->request(
//                'POST',
//                $uri,
//                [
//                    'headers' => [
//                        'Content-Type' => 'application/x-www-form-urlencoded'
//                    ],
//                    'body' => $queryString
//                ]
//            );
//        } catch(GuzzleException $e){
////            $this->logger->error($e->getMessage());
//            var_dump($e->getMessage());
//            return null;
//        }
//
//        if($response->getStatusCode() === 200){
//            $answer = json_decode($response->getBody()->getContents(), false);
////            var_dump($answer->next);
//            return $answer;
//        }
//
////        $this->logger->info('Unable to create lead. Response code : ' . $response->getStatusCode(), $queryArray);
//        return null;
//
//    }

    private function setLogger(){
        $this->logger = new Logger(
            'main_debug', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/main_debug.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}
