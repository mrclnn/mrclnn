<?php


namespace App\Http\Controllers;


use Throwable;

class GenshinAccessController
{
    public function exec(){
        try{
            $hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
            $username = 'borya.lalka2@gmail.com';
            $password = 'a375292961882';

            /* try to connect */
            $inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Gmail: ' . imap_last_error());
            $date = date('d F Y', strtotime( 'now - 300 day'));
//var_dump($date);
            $emails = imap_search($inbox,'FROM "noreply@email.mihoyo.com" SINCE "'.$date.'" BODY "Ваш код подтверждения"', SE_FREE, 'UTF-8');
//var_dump($emails);
            $mailID = max($emails);
            $overview = imap_fetch_overview($inbox,$mailID,0);
            $subject = imap_utf8($overview[0]->subject);
            echo '<pre>';
            var_dump($overview);
            imap_close($inbox);
        } catch (Throwable $e){
            var_dump($e->getMessage());
        }

    }
}