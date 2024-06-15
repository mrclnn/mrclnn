<?php
if(!isset($_GET['message'])) return;
$message = $_GET['message'];

function sendMessage(string $text){
    // Токен бота и идентификатор чата
    $token='1983935385:AAFzB9QwwYji8mS5PqwHbnKzt3j5qxeRvZc';
    $chat_id='1903458009';

// Отправить сообщение
    $ch=curl_init();
    curl_setopt($ch, CURLOPT_URL,
                'https://api.telegram.org/bot'.$token.'/sendMessage');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
                'chat_id='.$chat_id.'&text='.urlencode($text . ' FROM IP: '.$_SERVER['REMOTE_ADDR']));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

//// Настройки прокси, если это необходимо
//        $proxy='111.222.222.111:8080';
//        $auth='login:password';
//        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
//        curl_setopt($ch, CURLOPT_PROXY, $proxy);
//        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);

// Отправить сообщение
    if(isset($_GET['debug'])) sleep(5);
    $result=curl_exec($ch);
    curl_close($ch);
}
sendMessage($message);
