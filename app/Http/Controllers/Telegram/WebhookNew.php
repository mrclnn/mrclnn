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
            $author = json_decode(file_get_contents('php://input'))->message->from->first_name ?? null;
            $msgId = json_decode(file_get_contents('php://input'))->message->message_id ?? null;
            $this->chatId = json_decode(file_get_contents('php://input'))->message->chat->id ?? null;
            if(empty($this->chatId)) {
                $this->logger->error("Received empty chat id, reqeust: ", [json_decode(file_get_contents('php://input'))]);
                return;
            }
            $this->handleMessage($message, $author, $msgId);

        } catch (\Throwable $e){
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }
    }

    private function handleCommand(?string $message)
    {
        $command = preg_replace('/\s.*/', '', $message);

        $this->logger->debug('command: ', [$command]);
        $this->logger->debug('message: ', [$message]);
        try{
            switch ($command){
                case '/вопрос':
                    preg_match_all('%^/вопрос.+%ui', $message, $request);
                    $this->logger->debug('request: ', [$request]);
                    $request = collect($request)->first()[0] ?? '';
                    $request = preg_replace('%/ответ.+%ui', '', $request);
                    $this->logger->debug('request: ', [$request]);
                    $request = str_replace(['/вопрос', '/ответ'], '', $request);
                    $request = trim($request);
                    $this->logger->debug('request: ', [$request]);
                    if(empty($request)) throw new \InvalidArgumentException("Not found request message");

                    preg_match_all('/\/ответ\s.*$/ui', $message, $response);
                    $response = collect($response)->first()[0] ?? '';
                    $response = trim(str_replace(['/вопрос', '/ответ'], '', $response));
                    if(empty($response)) throw new \InvalidArgumentException("Not found response message");

                    DB::table('opezdal_dictionary')->insert(['request' => $request, 'response' => $response]);

                    $this->sendTextMessage('Фразу записала спасибо пожалуйста');

                    break;

                default:
                    $this->sendTextMessage('Не поняла, команду');
            }
        } catch (\Throwable $e){
            $this->sendTextMessage($e->getMessage());
        }

    }

    private function handleMessage(?string $message, ?string $author, ?int $msgId)
    {
        if(empty($message)) return;
        if(strpos($message, '/') === 0) $this->handleCommand($message);
        if($message === '300') $this->sendTextMessage("Отсоси у тракториста");

        $this->checkIsStickerTrigger($message, $msgId);

        if(strpos($message, 'опездал') !== 0) return;
        $message = trim(preg_replace(['/^опездал /', '/^,/'], '', $message));

        switch ($message){
            case 'test':
                $this->sendTextMessage('nothing to test');
                break;
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

                $response = DB::table('opezdal_dictionary')->where('request', 'like', "%$message%")->first()->response ?? 'Не поняла, блокирую!!!';
                $response = str_replace('{user}', $author, $response);
                $this->sendTextMessage($response);
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

    private function checkIsStickerTrigger(string $message, int $msgId)
    {
        $dic = [
            'м ага' => 'CAACAgIAAxkBAAEa4_1mxI0PTwPRl3-Jx9y_XChWdyZQlgACGVkAAuRk-ErxAva7XrmVrzUE',
            'кайнда' => 'CAACAgIAAxkBAAJuJ2bEh41doDRcBFR3OGrWSPYXVmEPAAKrSgACuuMBSz9BU8FmzCyGNQQ',
            'ультрахайп' => 'CAACAgIAAxkBAAEa5AFmxI2fKhtVVQpBk4BYiKtpOvVa9QACgVgAAsDT-EpU3taRYYaZQDUE',
            'мегахайп' => 'CAACAgIAAxkBAAEa5AFmxI2fKhtVVQpBk4BYiKtpOvVa9QACgVgAAsDT-EpU3taRYYaZQDUE',
            'гигахайп' => 'CAACAgIAAxkBAAEa5AFmxI2fKhtVVQpBk4BYiKtpOvVa9QACgVgAAsDT-EpU3taRYYaZQDUE',
            'гиперхайп' => 'CAACAgIAAxkBAAEa5AFmxI2fKhtVVQpBk4BYiKtpOvVa9QACgVgAAsDT-EpU3taRYYaZQDUE',
            'нормально нормально' => 'CAACAgIAAxkBAAEa5AdmxI3PI2FJ3In9s_D9NbwOtrn3agACt04AAmkm-Eq-3kI4A56XrTUE',
            'нас ебут' => 'CAACAgIAAxkBAAEa5AlmxI3qiZeUR9QhkHwU4Gv2GccgugACwVAAAtJB-Uo5qv6iRfGhHzUE',
            'меня ебут' => 'CAACAgIAAxkBAAEa5AlmxI3qiZeUR9QhkHwU4Gv2GccgugACwVAAAtJB-Uo5qv6iRfGhHzUE',
            'тебя ебут' => 'CAACAgIAAxkBAAEa5AlmxI3qiZeUR9QhkHwU4Gv2GccgugACwVAAAtJB-Uo5qv6iRfGhHzUE',
            'его ебут' => 'CAACAgIAAxkBAAEa5AlmxI3qiZeUR9QhkHwU4Gv2GccgugACwVAAAtJB-Uo5qv6iRfGhHzUE',
            'их ебут' => 'CAACAgIAAxkBAAEa5AlmxI3qiZeUR9QhkHwU4Gv2GccgugACwVAAAtJB-Uo5qv6iRfGhHzUE',
            'ее ебут' => 'CAACAgIAAxkBAAEa5AlmxI3qiZeUR9QhkHwU4Gv2GccgugACwVAAAtJB-Uo5qv6iRfGhHzUE',
            'её ебут' => 'CAACAgIAAxkBAAEa5AlmxI3qiZeUR9QhkHwU4Gv2GccgugACwVAAAtJB-Uo5qv6iRfGhHzUE',
//            'хайп' => 'CAACAgIAAxkBAAEa5A9mxI5dD7KvDYwRFYmnirButq7lWgACQlMAAmY7-Eq2MzZ1D4BkRTUE',
            'спасибо дура' => 'CAACAgIAAxkBAAEa5BVmxI6PhbqfuUYlR8XeFTjDXG9JowACHU0AAiL1WUoFfQX0HMr9uzUE',
            'хуяк хуяк' => 'CAACAgIAAxkBAAEa5BlmxI7KMk2EC6g7e6t9i4Vu_ggbawAChFIAAo7nAUtP2sqef8IOcjUE',
            'хуета' => 'CAACAgIAAxkBAAEa5BtmxI7fZoUAASprDbfqIPIfQ6g6LHQAAu1QAAIP1QFLNuGdZb2n34o1BA',
            'харош' => 'CAACAgIAAxkBAAEa5B1mxI7zUHfkWhm3PBNfm5P7CqgQrQACylUAAkdN-UqYkSirvP-dlTUE',
            'сюда' => 'CAACAgIAAxkBAAEa5B9mxI8DAAF0mQABV_kXSSx1yyyRR5iRAAKsSgACqmsBS8TFXSWMHYHjNQQ',
            'братья' => 'CAACAgIAAxkBAAEa5CFmxI8aOobJzIHI4N6hmg0VPtNcPAACAVIAAnEkAUskm-2Awi9DuTUE',
            'балдеж' => 'CAACAgIAAxkBAAEa5CNmxI8qVdevjkpxDcBwsEIp05oTcAACz1UAArDj-UoAAVpbZZ0Nr3A1BA',
            'балдёж' => 'CAACAgIAAxkBAAEa5CNmxI8qVdevjkpxDcBwsEIp05oTcAACz1UAArDj-UoAAVpbZZ0Nr3A1BA',
            'бэлдеж' => 'CAACAgIAAxkBAAEa5CNmxI8qVdevjkpxDcBwsEIp05oTcAACz1UAArDj-UoAAVpbZZ0Nr3A1BA',
            'факт' => 'CAACAgIAAxkBAAEa5CVmxI9HToh2G99T0uQhSAl86p6_DgACbFMAAojLqEpTmWEc-YJwgjUE',
            '??????????' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            '?????????' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            '????????' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            '???????' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            '??????' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            '?????' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            '????' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            '???' => 'CAACAgIAAxkBAAEa5CdmxI9dwT2-ybthBNwePcxfJQp39AACZkgAAl--qEpQX7eibdWKkTUE',
            'кринж' => 'CAACAgIAAxkBAAEa5ClmxI-Qn-xP3P8ZO0fhc7JK1aD0NgACCkYAAlzEYErBUr6_PSUPPjUE',
        ];
        foreach($dic as $search => $stick){
            if(str_contains(strtolower($message), $search)){
                $this->sendStickerMessage($stick, $msgId, $search);
                break;
            }
        }

    }

    private function sendStickerMessage(string $stickerFileId, ?int $msgId = null, ?string $quote): void
    {
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot'.env('TELEGRAM_API_KEY_OPEZDAL').'/sendSticker',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => $this->chatId,
                    'sticker' => $stickerFileId,
                    'reply_parameters' => json_encode([
                        'message_id' => $msgId,
                        'quote' => $quote
                    ])
                ),
            )
        );
        $res = curl_exec($ch);
//        $this->sendTextMessage($res);
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