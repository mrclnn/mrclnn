<?php

namespace App\Http\Controllers;

use App\Jobs\TestJob;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function execute(){
//        dispatch((new TestJob())->delay(Carbon::now()->addSeconds(2)));


        echo '5';
        $client = new Client();
        $response = $client->request('GET', 'https://api.av.by/offers/19267538/phones');
        $numbers = json_decode($response->getBody()->getContents());
        foreach($numbers as $number){
            var_dump($number);
        }
        dd();
//        die;

        $test = new TestJob();
        info('controller here');
        $test::dispatch()->delay(Carbon::now()->addSeconds(5));
    }
}
