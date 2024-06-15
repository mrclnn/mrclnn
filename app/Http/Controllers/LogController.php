<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;

class LogController extends Controller
{
    use LoggerAwareTrait;

    private $request;
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    public function execute(Request $request){
        $this->request = $request->all();
        $this->setLogger();

//        echo 'dsf';

        if(isset($this->request['message'])){
            $this->logger->debug('received request '. $this->request['message']);
//            sleep(20);
            $this->sendMessage($this->request['message']);
        }

        return new Response('ok', 200);
    }

    public function sendMessage(string $text){


// Отправить сообщение
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL,
                    'https://api.telegram.org/bot'.env('TELEGRAM_API_KEY_HENTAI').'/sendMessage');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
                    'chat_id='.env('TELEGRAM_MAIN_CHAT_ID').'&text='.urlencode($text));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

//// Настройки прокси, если это необходимо
//        $proxy='111.222.222.111:8080';
//        $auth='login:password';
//        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
//        curl_setopt($ch, CURLOPT_PROXY, $proxy);
//        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);

// Отправить сообщение
        $result=curl_exec($ch);
        curl_close($ch);
    }

    private function setLogger() : void
    {
        $this->logger = new Logger(
            'tgAPI', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/tgAPI.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}