<?php
$t = getopt('p:d:u:a:');
sendRoistatMetrics($t['p'], $t['d'], $t['u'], $t['a']);
function sendRoistatMetrics($callee, $caller, $duration, $date){

    $url = "https://cloud.roistat.com/api/v1/project/phone-call?project=191557&key=c11ef92048c3dc649955d5389e349d6b";
    $duration = (int)$duration;
    if ($duration >= 3) {
        $status = 'ANSWER';
    } elseif ($duration > 0) {
        $status = 'CANCEL';
    } else {
        $status = 'DONTCALL';
    }
    $postFields = json_encode((object)array(
        'callee' => preg_replace('%\D%', '', $callee),
        'caller' => preg_replace('%\D%', '', $caller),
        'duration' => $duration,
        'date' => $date,
        'status' => $status,
        'answer_duration' => $duration >= 3 ? $duration : 0,
    ));
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-Metric-Name: Roistat',
        'Accept: application/json',
        'Content-Type: application/json'
    ));

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($ch);
    var_dump($res);
    curl_close ($ch);

}