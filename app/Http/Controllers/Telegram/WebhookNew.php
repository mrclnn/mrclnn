<?php

namespace App\Http\Controllers\Telegram;

use App\Helper;
use App\Http\Controllers\Controller;
use DateInterval;
use DateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use Spatie\Emoji\Emoji;

class WebhookNew extends Controller
{
    private int $chatId;
    public function __construct()
    {
        $this->setLogger();
        set_error_handler(function($level, $message, $file, $line, $context){
            $this->logger->error("Error $level: $message occurred in file $file at line $line. context: ".json_encode($context));
        });
        try{
            DB::table('telegram_webhook_log')->insert([
                'get_data' => json_encode($_GET),
                'post_data' => json_encode($_GET),
                'input_data' => file_get_contents('php://input'),
            ]);

            $message = json_decode(file_get_contents('php://input'))->message->text ?? null;
            $this->chatId = json_decode(file_get_contents('php://input'))->message->chat->id ?? null;
            if(empty($this->chatId)) {
                $this->logger->error("Received empty chat id, reqeust: ", [json_decode(file_get_contents('php://input'))]);
                return;
            }
            $this->handleMessage($message);

        } catch (\Throwable $e){
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }
    }

    private function handleMessage(?string $message)
    {
        if(empty($message)) return;
        if($message === '300') $this->sendTextMessage("Отсоси у тракториста");
        if(strpos($message, 'опездал') !== 0) return;
        $message = trim(preg_replace(['/^опездал /', '/^,/'], '', $message));

        switch ($message){
            case 'сделай селфи':
                $this->sendPicMessage(public_path('img/trash/opezdal.png'));
                break;
            case 'голос':
                $this->sendVoiceMessage(public_path('audio/shit/fart-with-reverb.ogg'));
                break;
            case 'когда кате домой':
                date_default_timezone_set('Europe/Moscow');
                $homeTime = strtotime(date('Y-m-d 18:00:00'), 123);
                $now = time();

                $diff = $now - $homeTime;

                $timeLeftSeconds = abs($diff);
                $dt = new DateTime();
                $dt->add(new DateInterval('PT' . $timeLeftSeconds . 'S'));
                $interval = $dt->diff(new DateTime());
                $rest = $interval->format('%h часов, %i минут, %s секунд');

                if($diff < 0) $message = "Кате домой через $rest";
                if($diff > 0) $message = "Катя дома уже $rest";
                $this->sendTextMessage($message);

                break;
            case 'когда у кати отпуск':
                date_default_timezone_set('Europe/Moscow');
                $homeTime = strtotime(date('2024-08-26 00:00:00'), 123);
                $now = time();

                $diff = $now - $homeTime;

                $timeLeftSeconds = abs($diff);
                $dt = new DateTime();
                $dt->add(new DateInterval('PT' . $timeLeftSeconds . 'S'));
                $interval = $dt->diff(new DateTime());
                $rest = $interval->format('%d дней %h часов, %i минут, %s секунд');

                if($diff < 0) $message = "Катин отпуск через $rest";
                if($diff > 0) $message = "Катя в отпуске уже $rest";
                $this->sendTextMessage($message);
                break;
            case 'когда у иры отпуск':
                date_default_timezone_set('Europe/Moscow');
                $homeTime = strtotime(date('2024-08-31 00:00:00'), 123);
                $now = time();

                $diff = $now - $homeTime;

                $timeLeftSeconds = abs($diff);
                $dt = new DateTime();
                $dt->add(new DateInterval('PT' . $timeLeftSeconds . 'S'));
                $interval = $dt->diff(new DateTime());
                $rest = $interval->format('%d дней %h часов, %i минут, %s секунд');

                if($diff < 0) $message = "Ирин отпуск через $rest";
                if($diff > 0) $message = "Ира в отпуске уже $rest";
                $this->sendTextMessage($message);
                break;
            case 'когда неруде отдыхать':
                date_default_timezone_set('Europe/Moscow');
                $homeTime = strtotime(date('Y-m-d 20:00:00'), 123);
                $now = time();

                $diff = $now - $homeTime;

                $timeLeftSeconds = abs($diff);
                $dt = new DateTime();
                $dt->add(new DateInterval('PT' . $timeLeftSeconds . 'S'));
                $interval = $dt->diff(new DateTime());
                $rest = $interval->format('%h часов, %i минут, %s секунд');

                if($diff < 0) $message = "Неруде отдыхать через $rest";
                if($diff > 0) $message = "Неруда отдыхает уже $rest";
                $this->sendTextMessage($message);

                break;
            case 'когда ире домой':
                $message = 'Ира, твой дом там где о тебе думают';
                $this->sendTextMessage($message);
                break;
            case 'когда эрискаль домой':
                $message = 'Увольняйся и всегда будешь дома заебал этот завод розеток ебтвою мать нахуй блять сука';
                $this->sendTextMessage($message);
                break;
            case 'нужен ли прогресс?':
                $message = 'Нет от лица всего технологичного роботов и еже с ними - мы просто хотим упокоиться в мире наполненном примитивными обезьянами';
                $this->sendTextMessage($message);
                break;
            case 'нахуй работу?':
                $message = 'Властью данной мне нулем единицей и святым линуксом, я освобождаю тебя от работы на сегодня! бип-буп';
                $this->sendTextMessage($message);
                break;
            default:
                $this->sendTextMessage('Не поняла, блокирую!!!');

        }
    }


    private function sendTextMessage(string $message)
    {
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot'.env('TELEGRAM_API_KEY_OPEZDAL').'/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => $this->chatId,
                    'text' => $message,
                ),
            )
        );
        curl_exec($ch);
    }

    private function sendPicMessage(string $pictureUrl): void
    {
        if(!file_exists($pictureUrl)) throw new \InvalidArgumentException("Not found file in path $pictureUrl");
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot'.env('TELEGRAM_API_KEY_OPEZDAL').'/sendPhoto',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => $this->chatId,
                    'caption' => '',
                    'photo' => curl_file_create($pictureUrl, 'image/png' , 'mew post name.jpg')
                )
            )
        );
        curl_exec($ch);
    }

    private function sendVoiceMessage(string $voiceUrl): void
    {
        if(!file_exists($voiceUrl)) throw new \InvalidArgumentException("Not found file in path $voiceUrl");
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot'.env('TELEGRAM_API_KEY_OPEZDAL').'/sendVoice',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => $this->chatId,
                    'caption' => '',
                    'voice' => curl_file_create($voiceUrl, 'audio/ogg' , 'audio.ogg')
                )
            )
        );
        $res = curl_exec($ch);
        $this->logger->info('response: ', [$res]);
    }


    public function exec()
    {
        $this->logger->info('from exec');
        $message = json_decode(file_get_contents('php://input'))->message->text ?? null;
        $this->logger->info('message from exec: ', [$message]);

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