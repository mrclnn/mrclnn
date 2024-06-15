<?php

namespace App\Http\Controllers\Telegram;

use App\Helper;
use App\Models\Categories;
use App\Models\Posts;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Longman\TelegramBot\Telegram;
use Psr\Log\LoggerAwareTrait;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Spatie\Emoji\Emoji;

class Webhook extends Controller
{

    /**
     * Подключить бота можно перейдя по ссылке: (хуки сообщений входящих в бота подключает ссылка)
     * https://api.telegram.org:443/bot6863745477:AAGQ5lyDyrZjFx0ogivsFLiJOShQ_6pcWMk/setWebhook?url=https://mrlcnn.xyz/tg/hook
     */
    public function __construct()
    {
//        return;
        $this->setLogger();
        $this->logger->debug('from constructor');
        set_error_handler(function($level, $message, $file, $line, $context){
            $this->logger->error("Error $level: $message occurred in file $file at line $line. context: ".json_encode($context));
        });
//        $this->logger->debug(json_encode(file_get_contents('php://input')));
        try{
            $this->logger->info('received tg request');
            DB::table('telegram_webhook_log')->insert([
                'get_data' => json_encode($_GET),
                'post_data' => json_encode($_GET),
                'input_data' => file_get_contents('php://input'),
            ]);

            $message = json_decode(file_get_contents('php://input'))->message->text;

            if(is_numeric($message)){
                Helper::sendPost($message);
            } else {
                if(strpos($message, '/rand') === 0){
                    $quantity = explode(' ', $message)[1] ?? 0;
//                    Helper::log($quantity);
                    $quantity ? Helper::sendRandMediaGroup($quantity) : Helper::sendRandPost();
                    Helper::log('done');
                } else if(strpos($message, '/show') === 0){
                    $search = trim(str_replace('/show', '', $message));
                    Helper::log("search word: $search");
                    $category = Categories::getFromSearchTag($search);
                    $posts = $category->getPostsForSlider(10, 1);
                    Helper::sendMediaGroupByIds($posts->pluck('id'));
                } else if(strpos($message, '/load') === 0){
                    $message = trim(str_replace('/load', '', $message));
                    switch ($message){
                        default:
                            try{
                                $tag = $message;
                                DB::table('parsing_categories')->insert(['tag' => trim($tag)]);
                                Helper::log(Emoji::checkMarkButton()."$tag successfully added");
                            } catch (\Throwable $e){
                                Helper::log(Emoji::crossMark()."$tag already exists");
                            }
                    }
                } else {
                    Helper::log('Unknown command');
                }



            }







//            $tg = new Telegram(env('TELEGRAM_API_KEY'), env('TELEGRAM_BOT_NAME'));
//            $tg->handle();



        } catch (\Throwable $e){
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }
    }

    public function execute(Request $request)
    {
        //todo не понятно почему вебхук с тг не заходит в метод execute и останавливается в конструкторе
        $this->logger->info('from execute');
//        try{
//            $this->logger->info('received tg request');
//            DB::table('telegram_webhook_log')->insert([
//                'get_data' => json_encode($_GET),
//                'post_data' => json_encode($_GET),
//                'input_data' => json_encode(file_get_contents('php://input')),
//            ]);
//        } catch (\Throwable $e){
//            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
//        }

    }
    use LoggerAwareTrait;
    private function setLogger() : void
    {
        $logger_name = basename(str_replace('\\', '/', __CLASS__));
        $storage_sub_dir = basename(__DIR__);

        $this->logger = new Logger(
            $logger_name,
            [
                new PsrHandler(app()->make('log'), Logger::WARNING),
                new RotatingFileHandler(storage_path("logs/$storage_sub_dir/$logger_name.log"), 14, Logger::DEBUG, true, 0664),
            ],
            [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}
