<?php


namespace App\Jobs;

use App\Services\avtoport\AvbyAD;
use App\Services\avtoport\AvbyConfig;
use App\Services\Helper;
use App\Services\SiteParser;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerAwareTrait;
use simplehtmldom\HtmlWeb;


class SiteParserJob implements ShouldQueue
{
    use InteractsWithQueue;
    use LoggerAwareTrait;
    use Queueable;

    private $config;

    private $lastId;
    private $prev_id_list;
    private $lastExecTime;

    private $current_id_list = [];
    private $acceptableADs = [];

    public function __construct(string $lastId = null, array $prev_id_list = [], int $lastExecTime = 0)
    {
        $this->lastId = $lastId;
        $this->prev_id_list = $prev_id_list;
        $this->lastExecTime = $lastExecTime;
        $this->config = new AvbyConfig();
    }

    public function handle(SiteParser $parser) : void
    {
//        return;
//        $this->config = new AvbyConfig();
        $this->setLogger($parser->getLogger());
        if(!$this->config->valid){
            $this->logger->emergency("Unable to get av.by parser config. End work SiteParserJob");
            return;
        }
        if(!$this->config->enabled){
            if($this->lastId !== null){
                $parser->getLogger()->info('Stop parsing.');
            }
            $this->lastId = null;
            $this->current_id_list = [];
        } else {
            if((int)date('N') === $this->config->price_updater_period_day
                && (int)date('H') === $this->config->price_updater_period_time){
                $message = 'STOP PARSING, SWITCH TO PRICE CHECK...';
                $this->logger->info($message);
                Helper::sendImportantMessage('info', $message);
                dispatch(new AvtoportPriceUpdateJob());
                return;
            }

            $doc = new HtmlWeb();
            $currentPage = 1;
            $allCounter = 0;
            $this->logger->info('                                                  ');
            $this->logger->info('================ START PARSING... ================');
            try{

                while(true){
                    $url = 'https://cars.av.by/filter?sort=4&page=' . $currentPage;
                    $currentPageHTML = $doc->load($url);
                    if($currentPageHTML === null){
                        $this->logger->warning('Unable to get web page : ' . $url);
                        break;
                    }
                    foreach($currentPageHTML->find('div.listing-item') as $container){
                        $allCounter++;
                        try{
                            $ad = new AvbyAD($container, true);
                        } catch (Exception $e){
                            $this->logger->error('unable to parse ad '.$url.'. '. $e->getMessage().' at line '.$e->getLine());
                            continue;
                        }


                        if($this->breakCondition($ad)){
                            break 2;
                        }
                        if($this->continueCondition($ad)){
                            continue;
                        }

                        $this->current_id_list[] = $ad->id;

                        $this->acceptableADs[] = $ad;

                    }

                    $currentPage++;
                }

                if(!empty($this->current_id_list)){
                    $this->lastId = $this->current_id_list[0];
                }
                $this->logger->debug($this->cuteMsg(sprintf('Found %s/%s acceptable ADs', count($this->current_id_list), $allCounter)));
                $this->logger->info('================ PARSING COMPLETE ================');
                if(empty($this->acceptableADs)){
                    $this->logger->info('Nothing to send. Skip');
                } else {
                    dispatch(new SendToBXJob($this->acceptableADs));
                }


            } catch (Exception $e){
                // TODO пока не будет решена проблема при которой сообщение об ошибке слишком объемно. сериализация данных в job
//                $message = $e->getMessage() . $e->getFile() . $e->getLine();
//                if(strlen($message) > 2000) $message = substr($message,0,2000);
//                $this->logger->error($message);
            }

        }

        $job = new SiteParserJob($this->lastId, $this->current_id_list, time());
        dispatch($job->delay(Carbon::now()->addSeconds($this->config->parsing_period)));

    }


    private function isDuplicateId(string $id): bool
    {
        if(!is_array($this->prev_id_list)) return false;
        return in_array($id, $this->prev_id_list);
    }

    private function isAcceptablePubDate(AvbyAD $ad) : bool
    {
        // к периоду добавляетсяя collisionTime чтобы случайно не отсечь те объявления которые были созданы в промежутке,
        // когда скрипт исполнялся (5-10 секунд)
        // если не будет найден id то повторяющиеся значения в любом случае будут отсечены в ContinueCondition.
        $collisionValue = 30;
        $minAcceptableTimestamp = $this->lastExecTime === 0
            ? time() - ($this->config->parsing_period + $collisionValue)
            : $this->lastExecTime - $collisionValue;

        return $ad->timestamp > $minAcceptableTimestamp;
    }

    private function breakCondition(AvbyAD $ad) : bool
    {

        // проверка первого запуска
        if($this->lastId === null){
            $this->lastId = $ad->id;
            $this->logger->debug($this->cuteMsg('This is first start. Stop container id : ' . $this->lastId));
            return true;
        }

        // проверка по id
        if($ad->id === $this->lastId){

            $this->logger->debug($this->cuteMsg('Break because last element found.'));
            return true;
        }

        // проверка по дате объявления
        if(!empty($this->lastId) && !$this->isAcceptablePubDate($ad) && !$ad->isVipContainer){

            $this->logger->debug($this->cuteMsg('Break because ad is too old: ' . $ad->pub_date));
            return true;

        }

        // проверка по кол-ву обработанных объявлений
        if(count($this->current_id_list) >= $this->config->id_list_limit){

            $this->logger->debug($this->cuteMsg('Break because limit of ads ('.$this->config->id_list_limit.') reached. pub date of last ad : ' . $ad->pub_date));
            return true;

        }

        return false;
    }

    private function continueCondition(AvbyAD $ad) : bool
    {
        return
            ($ad->isJuridicalAD)
//            || ($ad->price_usd < $this->config->ad_min_price)
            || ((int)preg_replace('/\D/', '', $ad->year) < $this->config->ad_min_year)
            || ($ad->isPartsBadge)
            || ($ad->isWreckBadge)
            || ($ad->isVipContainer && !$this->isAcceptablePubDate($ad))
            || ($this->isDuplicateId($ad->id));
//            || ($ad->price_diff > $this->config->max_acceptable_price);

    }

    private function cuteMsg(string $message) : string
    {
        return str_pad($message, 49, ' ', STR_PAD_RIGHT);
    }

}
