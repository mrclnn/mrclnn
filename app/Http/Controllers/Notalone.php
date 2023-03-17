<?php


namespace App\Http\Controllers;


use Illuminate\Support\Facades\DB;

class Notalone
{
    public function execute(){
        return view('notalone');
    }
    private function sync(){
        return [
            'paused' => false,
            'time' => 0
        ];
    }
    private function getCurrentTime(){

    }
    private function pause(){

    }
    private function isPaused(){

    }
}