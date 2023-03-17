<?php


namespace App\Services;


use Illuminate\Support\Facades\DB;

class NotaloneService
{
    public static function process($request){
        if(isset($request['setPause'])){
            $time = (double)$request['time'];
            DB::select("UPDATE notalone SET isPaused = 1, time = $time WHERE id = 1");
            return ['success' => true];
        };
        if(isset($request['setPlay'])){
            DB::select('UPDATE notalone SET isPaused = 0 WHERE id = 1');
            return ['success' => true];
        };
        if(isset($request['setTime'])){
            $time = (int)$request['setTime'];
            DB::select("UPDATE notalone SET time = $time WHERE id = 1");
            return ['success' => true];
        }
        if(isset($request['sync'])){
            $res = DB::select("SELECT time, isPaused FROM notalone WHERE id = 1");
            return [
                'paused' => $res[0]->isPaused,
                'time' => $res[0]->time
            ];

        };
        return ['success' => true];
    }
}