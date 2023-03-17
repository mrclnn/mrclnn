<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitorController extends Controller
{
    public function execute(){
//        echo 'monitor<br>';

        return view('monitor');

    }
    private function sendRequest(array $params){
        //TODO спрятать конфиденциальную инфо
        $ch = curl_init('https://ats2bx24.it-center.by/logger?info&' . http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('user-agent: mrclnn'));
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $result = curl_exec($ch);
        if(curl_error($ch)) {
            echo curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }

    private function parseString(string $logString){
        $string = [];
        // 2021-04-14 11:57:40.1000 // 24
        // 2021-04-14 11:57:40.684  // 23
        preg_match('/^.{23,24} /', $logString, $date);
        if(!isset($date[0])){
            echo $logString;
            echo '<br>empty date<br>';
            return null;
        }
        $string['date'] = trim($date[0]);
        $logString = preg_replace('/^.{23,24} /', '', $logString);
        preg_match('/^\[.{23}] /', $logString, $uniqueid);
        if(!isset($uniqueid[0])){
            echo $logString;
            echo '<br>empty $uniqueid<br>';
            return null;
        }
        $string['uniqueid'] = trim(preg_replace('/[\[\]]/', '', $uniqueid[0]));
        $logString = preg_replace('/^\[.{23}] /', '', $logString);
        preg_match('/^\[[A-Z]+]/', $logString, $level);
        if(!isset($level[0])){
            echo $logString;
            echo '<br>empty $level<br>';
            return null;
        }
        $string['level'] = preg_replace('/[\[\]]/', '', $level[0]);
        $logString = preg_replace('/^\[[A-Z]+]/', '', $logString);
        $string['message'] = $logString;

        return $string;
    }

    private function insertStringIntoDB(array $string){
        $insert = [
            'date' => $string['date'],
            'uniqueid' => $string['uniqueid'],
            'level' => $string['level'],
            'message' => $string['message']
        ];
        DB::table('monitor')->insert($insert);
    }

    private function getLogFile(string $client, string $date){
        $params = [
            'info_params' => [
                'client' => $client,
                'date' => $date
            ]
        ];
        $result = json_decode($this->sendRequest($params), false);
        if(!$result->success){
            throw new Exception('Enable to get log file: '.$result->reason);
        }
        $fileString = $result->content;
        $res = explode(PHP_EOL, $fileString);

        foreach($res as $id => $string){
            if(!$string) continue;
            $logString = $this->parseString($string);
            if($logString){
                $this->insertStringIntoDB($logString);
            } else {
                echo "$id/".count($res);
                return false;
            }
        }
        return true;
    }
}
