<?php


namespace App\Jobs;


use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use simplehtmldom\HtmlWeb;

class BXUpdaterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LoggerAwareTrait;
    public $timeout = 240;

    private $offset;
    private $doc;
    private $correctCounter;

    private $curl;

    public function __construct(int $offset = 0, int $correctCounter = 0)
    {
        $this->offset = $offset;
        $this->correctCounter = $correctCounter;
        $this->doc = new HtmlWeb();
    }

    public function handle() : void
    {
        $this->setLogger();

        $leads = $this->getLeads($this->offset);
        if($leads !== null){


            $this->curl = curl_init('https://cars.av.by');

            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

            $headers = array(
                "Connection: keep-alive",
                "Keep-Alive: timeout=5, max=100",
            );
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);


            $this->logger->debug(sprintf('Checking %s/%s leads...', $leads->next - 50, $leads->total));
            $this->doc = new HtmlWeb();
            foreach($leads->result as $i => $lead){
                if($i < $this->correctCounter) continue;
                $timeBegin = microtime(true);
                $link = $lead->UF_CRM_1611743652968;
                if(!$link){
                    $this->logger->warning('empty link for lead '.$lead->ID);
                    continue;
                }
                $reason = '';
                $isValid = $this->checkValid($link, $reason);
                $timeEnd = (string)round(microtime(true) - $timeBegin, 1);
                $timeEnd = strlen($timeEnd) < 3 ? $timeEnd.'.0' : $timeEnd;
                if(!$isValid){
                    $this->logger->info(sprintf('(%s) (%s) INVALID: %s Lead %s.',$timeEnd, memory_get_usage(), $reason, $lead->UF_CRM_1611743652968));
                    $this->deleteLead($lead->ID);
                    continue;
                }
                $this->logger->info(sprintf('(%s) (%s) VALID Lead %s. skip.',$timeEnd, memory_get_usage(), $lead->UF_CRM_1611743652968));
                $this->correctCounter++;
            }


            curl_close($this->curl);
        } else {
            $this->logger->error('received null leads. retry 60 sec');
            self::dispatch($this->offset, $this->correctCounter)->delay(Carbon::now()->addSeconds(60));
            return;
        }

        if($this->correctCounter === 50){
            $this->offset = $leads->next;
            $this->correctCounter = 0;
        }

        if($this->offset !== null){
            self::dispatch($this->offset, $this->correctCounter);
        } else {
            $this->logger->info('received only '. count($leads->result) . ' last leads. end work');
        }

    }

    private function checkValid(string $link, string &$reason): bool
    {


        curl_setopt($this->curl, CURLOPT_URL, $link);
        $resp = substr(curl_exec($this->curl), 21000, 20000);

        if(strpos($resp, '<div class="gallery__status"><span>Удалено</span>') !== false){
            $reason = 'deleted ad';
            return false;
        }
        if(strpos($resp, '<div class="gallery__status"><span>Продано</span>') !== false) {
            $reason = 'sold';
            return false;
        };

        return true;

    }

    private function getLeads(int $offset = 0){
        $uri = 'https://b24-il9uen.bitrix24.by/rest/1/pygj6lau7vixoam1/crm.lead.list.json';

        $queryArray = [
            'order' => [
                'DATE_CREATE' => 'DESC'
            ],
            'filter' => [
                'STATUS_ID' => 'NEW'
            ],
            'select' => [
                'ID', 'UF_CRM_1611743652968'
            ],
            'start' => $offset
        ];
        $client = new Client();
        $queryString = preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($queryArray));
        try{
            $response = $client->request(
                'POST',
                $uri,
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'body' => $queryString
                ]
            );
        } catch(GuzzleException $e){
            $this->logger->error($e->getResponse()->getBody()->getContents());
            return null;
        }
        return json_decode($response->getBody()->getContents());

    }

    private function deleteLead(int $id){
        $uri = 'https://b24-il9uen.bitrix24.by/rest/1/pygj6lau7vixoam1/crm.lead.delete.json';

        $queryArray = [
            'id' => $id
        ];
        $client = new Client();
        $queryString = preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($queryArray));
        try{
            $response = $client->request(
                'POST',
                $uri,
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'body' => $queryString
                ]
            );
        } catch(GuzzleException $e){
            $this->logger->error(json_decode($e->getResponse()->getBody()->getContents()));
            return null;
        }
        return json_decode($response->getBody()->getContents())->result;
    }

    private function setLogger(){
        $this->logger = new Logger(
            'BXUpdaterLogger', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/BXUpdater.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}