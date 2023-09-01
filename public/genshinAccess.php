<?php
if($_GET['fsayiw7eufhaiwfiaeufiasefjk'] === 'faimw8efi8o37fw8a83uf8afoiwdk'){
    try{
        $hostname = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
        $username = 'borya.lalka2@gmail.com';
        $password = 'a375292961882';

        /* try to connect */
        $inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Gmail: ' . imap_last_error());
        $date = date('d F Y', strtotime( 'now - 1 day'));
        //var_dump($date);
        $emails = imap_search($inbox,'FROM "noreply@email.mihoyo.com" SINCE "'.$date.'" BODY "Ваш код подтверждения"', SE_FREE, 'UTF-8');
        //var_dump($emails);
        if($emails){
            $mailID = max($emails);
            $overview = imap_fetch_overview($inbox,$mailID,0);
            $subject = imap_utf8($overview[0]->subject);

//        echo '<pre>';
//        var_dump($subject);
//            $messageDate = date('Y.m.d', strtotime($overview[0]->date));
//        echo "<p>Дата письма: $messageDate</p>";
            echo "<h1>$subject</h1>";

        } else {
            echo '<h1>За последние сутки писем с кодом подтверждения не было</h1>';
        }
        imap_close($inbox);

    } catch (Throwable $e){
        var_dump($e->getMessage());
    }
}
