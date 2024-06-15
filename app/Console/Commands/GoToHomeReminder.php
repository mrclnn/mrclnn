<?php

namespace App\Console\Commands;

use App\Helper;
use Illuminate\Console\Command;

class GoToHomeReminder extends Command
{
    protected $signature = 'goToHomeReminder';
    private int $chatId = -4050337747;
    public function handle(): void
    {
//        $this->sendReminder("Ира шли их нахуй они все пидарасы 100% бип-буп");
//        return;
        date_default_timezone_set('Europe/Moscow');
        $hour = date('H');
        $minutes = date('i');
        if($hour === '17') $message = 'Катя тебе домой через '.(60 - $minutes).' минут!!!';
        if(+$hour > 18) $message = 'Катя ты уже дома?';
//        Helper::log($message);
        if(isset($message)) $this->sendReminder($message);
    }

    private function sendReminder(string $message)
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
}