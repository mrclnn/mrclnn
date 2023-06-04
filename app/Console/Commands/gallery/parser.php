<?php

namespace App\Console\Commands\gallery;

use App\GalleryImage;
use App\Jobs\ParserJobConfig;
use App\Models\Categories;
use App\ParserAggregator;
use Illuminate\Console\Command;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class parser extends Command
{
    use LoggerAwareTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gallery:parse {category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->setLogger();
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try{
            echo "this is gallery parser\n";
            $category = $this->argument('category');

            $parserConfig = new ParserJobConfig('rule34', $category);

            do{
                sleep(2);
                $categoryParser = ParserAggregator::getParser('category');
                if(!$categoryParser){
                    $this->logger->error("Received invalid Parser for request word 'pagination', check config. End work.");
                    echo "Received invalid Parser for request word 'pagination', check config. End work.\n";
                    return;
                }

                $categoryParsingData = $categoryParser->parse([
                    'pid' => $parserConfig->pid,
                    'tag' => $parserConfig->category,
                    'filter' => $parserConfig->filter
                ]);

                $parserConfig->processPagination($categoryParsingData['pagination']);
                $parserConfig->processContent($categoryParsingData['posts']);

                $lastPage = $parserConfig->lastPage;
                $currentPage = $parserConfig->iteration + 1;
                $category = $parserConfig->category;
                $this->logger->info("Start parsing $category, page $currentPage/$lastPage...");
                echo "Start parsing $category, page $currentPage/$lastPage...\n";

                $restCount = count($parserConfig->needleIds);
                $existedCount = count($categoryParsingData['posts']) - $restCount;
                $this->logger->info("$existedCount posts already parsed, $restCount to go...");
                echo "$existedCount posts already parsed, $restCount to go...\n";

                //todo success count добавить сюда + запись в логи после парсинга всех постов
                foreach($parserConfig->needleIds as $i => $id){
                    sleep(2);
                    $postParser = ParserAggregator::getParser('post');
                    if(!$postParser){
                        $this->logger->error("Received invalid Parser for request word 'post', check config. End work.");
                        echo "Received invalid Parser for request word 'post', check config. End work.\n";
                        return;
                    }
                    $postParsingData = $postParser->parse(['postId' => (int)$id]);
                    $img = (new GalleryImage())->fillFromHtmlDocument($postParsingData);
                    if(!$img){
                        $this->logger->error("Skip post $id: Received null GalleryImage instance, invalid postParsingData.");
                        echo "Skip post $id: Received null GalleryImage instance, invalid postParsingData.\n";
                        continue;
                    }
                    //todo эта проверка уже избыточна, потому что в конфиге мы готовим список id постов которых нет
                    if($img->isExist()){
                        $this->logger->info("Skip post $id: Already exist.");
                        echo "Skip post $id: Already exist.\n";
                        continue;
                    }
                    try{
                        $success = $img->save()->writeToDB();
                        if($success){
                            $this->logger->info("post $id parsed successfully.");
                            echo "post $id parsed successfully.\n";
                        } else {
                            //todo нужна дополнительная информация о неудаче
                            $this->logger->info("Unable to parse $id");
                            echo "Unable to parse $id\n";
                        }

                    } catch (Throwable $e){
                        $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
                        echo "{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}\n";
                    }

                }

                $parserConfig->prepareNextIteration();

            } while (!$parserConfig->isItLastIteration);

            $message = sprintf(
                "All %s/%s for category %s pages is parsed.",
                $parserConfig->iteration,
                $parserConfig->lastPage,
                $parserConfig->category
            );
            $this->logger->info($message);
            echo "$message\n";
            $this->logger->info('Start recount enabled categories...');
            echo "Start recount enabled categories...\n";
            $enabledCategories = Categories::getEnabled();
            foreach($enabledCategories as $category){
                $oldValue = $category->count;
                $category->reCount()->save();
                $newValue = $category->count;
                $this->logger->info("Category $category->name updated: $oldValue->$newValue posts.");
                echo "Category $category->name updated: $oldValue->$newValue posts.\n";
            }
            $this->logger->info('End work');
            echo "End work\n";


        } catch (\Throwable $e){
            echo "{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}\n";
        }

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
