<?php


namespace App;


class Helper
{
    public function sendToTG(string $message){
        $ch = curl_init();
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_URL => 'https://api.telegram.org/bot1870702904:AAFEsvY_Gy0E6lSrJTR3exGv2xWRJkyAZjQ/sendMessage',
                CURLOPT_POST => TRUE,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_POSTFIELDS => array(
                    'chat_id' => '438500729',
                    'text' => $message,
                ),
            )
        );
        curl_exec($ch);
    }
}