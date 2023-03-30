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


    private ?ParserJobConfig $config;

    public function __construct(?ParserJobConfig $config = null)
    {
        //todo разобраться с сериализацией функций в конструкторе
        // он не хочет здесь принимать сложные данные
//        $this->setLogger();
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
        $categoryParsingData = $categoryParser->parse([$this->config->pid, $this->config->category, $this->config->filter]);

        $this->config->processPagination($categoryParsingData['pagination']);
        $this->config->processContent($categoryParsingData['posts']);




        $lastPage = $this->config->lastPage;
        $currentPage = $this->config->iteration + 1;
        $category = $this->config->category;
        $this->logger->info("Start parsing $category, page $currentPage/$lastPage...");
        $restCount = count($this->config->needleIds);
        $existedCount = count($categoryParsingData['posts']) - $restCount;
        $this->logger->info("$existedCount posts already parsed, $restCount to go...");

        //todo success count добавить сюда + запись в логи после парсинга всех постов
        foreach($this->config->needleIds as $i => $id){
            sleep(2);
            $postParser = ParserAggregator::getParser('post');
            if(!$postParser){
                $this->logger->error("Received invalid Parser for request word 'post', check config. End work.");
                return;
            }
            $postParsingData = $postParser->parse([(int)$id]);
            $img = (new GalleryImage())->fillFromHtmlDocument($postParsingData);
            if(!$img){
                $this->logger->error("Skip post $id: Received null GalleryImage instance, invalid postParsingData.");
                continue;
            }
            //todo эта проверка уже избыточна, потому что в конфиге мы готовим список id постов которых нет
            if($img->isExist()){
                $this->logger->info("Skip post $id: Already exist.");
                continue;
            }
            try{
                $success = $img->save();
                if($success){
                    $img->writeToDB();
                    $this->logger->info("post $id parsed successfully");
                } else {
                    //todo нужна дополнительная информация о неудаче
                    $this->logger->info("Unable to parse $id");
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