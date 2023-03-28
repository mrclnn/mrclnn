<?php

namespace App\Jobs;

use App\GalleryImage;
use App\ParserAggregator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use simplehtmldom\HtmlWeb;
use Throwable;

class ParserJobNew implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LoggerAwareTrait;


    private int $iteration;
    private ?ParserJobConfig $config;

    public function __construct(?ParserJobConfig $config = null, int $iteration = 0)
    {
        //todo разобраться с сериализацией функций в конструкторе
        // он не хочет здесь принимать сложные данные
//        $this->setLogger();
        $this->iteration = $iteration;
        // parserJobConfig должен быть правильным, иначе job не должна выполняться
        $this->config = $config;
    }

    public function handle()
    {
        $this->setLogger();
        $categoryParser = ParserAggregator::getParser('category');
        if(!$categoryParser){
            $this->logger->error("Received invalid Parser for request word 'pagination', check config. End work.");
            return;
        }
        //todo нужно упорядочить аргумент передаваемый в parse(); по сути это просто список гет значений для URL парсера
        // соответственно нужно добавить поле "required" или типа того где указано каким должен быть аргумент
        // кто будет конструировать объект-аргумент?
        $categoryParsingData = $categoryParser->parse([$this->config->pid, $this->config->category]);


        if(!isset($this->config->lastPage)){
            //todo возможно нужно как-то различать ситуацию где в категории только одна страница и когда не удалось получить навигацию в принципе
            if(empty($categoryParsingData['pagination'])){
                $this->config->lastPage = 1;
            } else {
                $lastPageHref = $categoryParsingData['pagination'][0]->attr['href'];
                preg_match('/\d+$/', $lastPageHref, $lastPage);
                if(!isset($lastPage) or empty($lastPage[0])){
                    $this->logger->error('Not found last page. End work');
                    return;
                }

                $this->config->lastPage = (int)$lastPage[0] / 42;
            }
        }

        $lastPage = $this->config->lastPage;
        $currentPage = $this->config->iteration + 1;
        $category = $this->config->category;
        $this->logger->info("Start parsing $category, page $currentPage/$lastPage...");

        // как описать цикл именно по ключу posts ?
        //todo реализовать это красивее
        $alreadyExistCount = 0;
        foreach($categoryParsingData['posts'] as $i => $postHTML){
            $postURI = $postHTML->attr['href'];
            if(!$postURI){
                $this->logger->error('Skip post: URI not found');
                continue;
            }
//            $this->logger->info("Parsing post: $postURI");
            $idFound = preg_match('/\d+$/', $postURI, $id) === 1;
            if(!$idFound){
                $this->logger->error("Skip post: ID not found ($postURI)");
                continue;
            }
            sleep(2);
            $postParser = ParserAggregator::getParser('post');
            if(!$postParser){
                $this->logger->error("Received invalid Parser for request word 'post', check config. End work.");
                return;
            }
            $postParsingData = $postParser->parse([(int)$id[0]]);
            $img = (new GalleryImage())->fillFromHtmlDocument($postParsingData);
            if(!$img){
                $this->logger->error("Skip post: Received null GalleryImage instance, invalid postParsingData.");
                continue;
            }
            if($img->isExist()){
                $this->logger->info("Skip post: Already exist ($postURI)");
                $alreadyExistCount++;
                $limit = 3;
                if($alreadyExistCount > $limit){
                    $this->logger->info("Found $limit existed posts in a row. skip current page...");
                    break;
                } else {
                    continue;
                }

            }
            try{
                $success = $img->save();
                if($success){
                    $img->writeToDB();
                    $this->logger->info("Post $i parsed successfully: $postURI");
                } else {
                    $this->logger->info("Unable to parse $i : $postURI");
                }

            } catch (Throwable $e){
                $this->processError($e);
            }

        };

        $this->config->prepareNextIteration();
        if($this->config->isItLastIteration){
            $this->logger->info("All $currentPage/$lastPage pages is parsed, end work.");
        } else {
            $this->logger->info("Go to next iteration...");
            self::dispatch($this->config)->delay(Carbon::now()->addSeconds($this->config->delay));
        }

    }

    private function continueCondition(): bool
    {
        //todo написать метод
        return true;
    }
    private function continue()
    {
        //todo написать метод
        // тут должны быть dispatch конструкции
    }


    private function processError(Throwable $e){
        $this->logger->error(sprintf('%s in %s at line %s', $e->getMessage(), $e->getFile(), $e->getLine()));
    }

    private function setLogger(){
        $this->logger = new Logger(
            'SiteParser', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/parser.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}