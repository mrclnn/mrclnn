<?php

namespace App\Console\Commands\gallery;

use App\GalleryImage;
use App\Jobs\ParserJobConfig;
use App\Models\Categories;
use App\Parser;
use App\ParserAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class ParserCommand extends Command
{
    use LoggerAwareTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gallery:parse {source=null} {category=null}';

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
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $this->setLogger();
        date_default_timezone_set('Europe/Moscow');

        try{

            $category = $this->argument('category');
            $source = $this->argument('source');
            //todo это бред
            if($source === 'null' && $category === 'null'){

                echo 'here';

                while($parsingCategory = DB::table('parsing_categories')->where('status', null)->orWhere('status', Parser::STATUS_BUSY)->first()){

                    DB::table('parsing_categories')->where('id', $parsingCategory->id)->update([
                        'status' => Parser::STATUS_BUSY,
                        'updated_at' => now(),
                    ]);

                    echo "Received from standart parsing queue category: $parsingCategory->tag, source: $parsingCategory->source\n";
                    $this->process($parsingCategory->source, $parsingCategory->tag);

                    DB::table('parsing_categories')->where('id', $parsingCategory->id)->update([
                        'status' => Parser::STATUS_LOADED,
                        'updated_at' => now(),
                        'uploaded_at' => now(),
                    ]);

                }


            } else {
                echo "Received from parameters category: $category, source: $source\n";
                $this->process($source, $category);
            }

        } catch (\Throwable $e){

            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");

        }

    }

    private function process(string $source, string $category)
    {
        try{

            $parserConfig = new ParserJobConfig($source, $category);

            $alreadyParsedIndex = 0;
            do{
                if($alreadyParsedIndex > 2){
                    $this->logger->info("Already parsed pages limit $alreadyParsedIndex reached, exit");
                    break;
                }
                sleep(2);
                $categoryParser = ParserAggregator::getParser('category');
                if(!$categoryParser){
                    $this->logger->error("Received invalid Parser for request word 'pagination', check config. End work.");
                    echo "Received invalid Parser for request word 'pagination', check config. End work.\n";
                    return;
                }

                do{
                    $categoryParsingData = $categoryParser->parse([
                        'pid' => $parserConfig->pid,
                        'tag' => $parserConfig->category,
                        'filter' => $parserConfig->filter
                    ]);

                    if(!$categoryParsingData){
                        $delay = 3 * 60; //5 min
                        echo 'sleep '.($delay/60)." min\n";
                        for($minutesRest = $delay / 60; $minutesRest > 0; $minutesRest--){
                            echo "next try in $minutesRest minutes...\n";
                            sleep($delay / ( $delay/60));
                        }
                    }

                }while(!$categoryParsingData);

                $parserConfig->processPagination($categoryParsingData['pagination']);
                $parserConfig->processContent($categoryParsingData['posts']);

                $lastPage = $parserConfig->lastPage;
                $currentPage = $parserConfig->iteration + 1;
                $category = $parserConfig->category;
                $this->logger->info("Start parsing $category, page $currentPage/$lastPage...");
                echo "Start parsing $category, page $currentPage/$lastPage...\n";

                $restCount = count($parserConfig->needleIds);
                $existedCount = count($categoryParsingData['posts']) - $restCount;
                if($existedCount === $restCount) $alreadyParsedIndex++;
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
                    do{
                        $postParsingData = $postParser->parse(['postId' => (int)$id]);
                        if(!$postParsingData){
                            echo "403 for $id, waiting...\n";
                            $delay = 3 * 60; //5 min
                            echo 'sleep '.($delay/60)." min\n";
                            for($minutesRest = $delay / 60; $minutesRest > 0; $minutesRest--){
                                echo "next try in $minutesRest minutes...\n";
                                sleep($delay / ( $delay/60));
                            }
                        }
                    } while(!$postParsingData);

//                    $this->logger->debug(json_encode($postParsingData));

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
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
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
