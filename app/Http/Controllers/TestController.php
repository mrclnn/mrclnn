<?php

namespace App\Http\Controllers;

use App\Jobs\TestJob;
use App\Models\Categories;
use Carbon\Carbon;
use Dadata\DadataClient;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function longProcess(Request $request)
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'null');
//        set_time_limit(0);
//        sleep(1000);
//        return 'ok!';
    }
    public function execute(Request $request){

        return ($_SERVER['REQUEST_METHOD'] ?? 'null');

//        $array = [1,2,3,'', array(), null, false, 0, '1241'];
//        $array = array_filter($array, function($val){ return !empty($val); });
//
//        dump($array);
        return;


//        dispatch((new TestJob())->delay(Carbon::now()->addSeconds(2)));



//        $category = (new Categories)
//            ->setExtendTags($request->input('extendTags'))
//            ->setExcludeTags($request->input('excludeTags'))
//            ->setIncludeTags($request->input('includeTags'))
//            ->setName($request->input('name'))
//            ->setCount($request->input('count'));
////        $res = $category->save();
////        dump($res);
//        $category->include_tags = '123,123,123';
//
//        dd($category);

        $search = 'село Агой';

        $token = '81bc819cc77ccd6ffaf04099b60c6fd06af82db6';
        $dadata = new DadataClient($token, null);
        $result = $dadata->suggest('address', $search);
//        $result = $dadata->findById("party", "332906014469", 5);
        dump($result);

        die;



        $this->avitoTokenTest();
        dd('test ');


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

    private function avitoTokenTest()
    {
        $clientId = 'ifmsmITVGJUkR7EKK8QO';
        $clientSecret = '1EXfrzI2qmRSm-JpKtzMFDXj3oMf85csGOTp0Frg';

        $headers = [];
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';

        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials'
        ];
        $data = http_build_query($data);

        $requestUrl = 'https://api.avito.ru/token/';

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client-Amopoint/1.0');

        curl_setopt($curl, CURLOPT_URL, $requestUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        dump($code);
        dump($response);
    }
}
