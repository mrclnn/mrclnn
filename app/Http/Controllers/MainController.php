<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use App\GalleryCategoryAggregator;
use App\GalleryCategoryModel;
use App\GalleryImage;
use App\GalleryPostAggregator;
use App\GalleryPostModel;
use App\GalleryTagAggregator;
use App\GalleryTagModel;
use App\Helper;
use App\Jobs\AVBYtmpJob;
use App\Jobs\BXUpdaterJob;
use App\Jobs\ParserConfig;
use App\Jobs\ParserJob;
use App\Jobs\ParserJobConfig;
use App\Jobs\ParserJobNew;
use App\Jobs\SiteParser;
use App\Jobs\TestJob;
use App\Jobs\TmpJob;
use App\lib\AmoCrmApi\AmoCrmApi;
use App\Models\Categories;
use App\Models\Posts;
use App\Models\Tags;
use App\Parser;
use App\ParserAggregator;
use App\Product_types;
use App\Services\bbgClientApi;
use DateTime;
use DateTimeZone;
use FORMAT;
use Generator;
use Google\Client;
use Google\Service\Drive;
use Google\Task\Composer;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ImageHash;
use Illuminate\View\View;
use Imagick;
use InvalidArgumentException;
use Leonied7\Yandex\Disk;
use LogicException;
use mikehaertl\wkhtmlto\Pdf;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use morphos\Currency;
use morphos\Russian\MoneySpeller;
use pepeEpe\FastImageCompare\ComparatorFileCrc32b;
use pepeEpe\FastImageCompare\ComparatorImageMagick;
use pepeEpe\FastImageCompare\FastImageCompare;
use pepeEpe\FastImageCompare\IComparable;
use pepeEpe\FastImageCompare\NormalizerSquaredSize;
use Phar;
use Psr\Log\LoggerAwareTrait;
use ReflectionClass;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlNode;
use simplehtmldom\HtmlWeb;
use Throwable;
use function morphos\Russian\inflectName;

//use function morphos\Russian\inflectName;

//use function 'app/lib/Morphos-master/src/Russian/functions.php';

class MainController extends Controller
{
    use LoggerAwareTrait;
    private $filters = [];
    private $offset = 0;
    private $doc;

    public function __construct()
    {
//        $this->middleware('auth');
    }

    private function displayImages(array $res)
    {
        $img = array_map(function($path){
            return str_replace('D:\pr\OSPanel\domains\mrclnn\public\\', '', "<img style='width: 20%' src='$path'>");
        }, $res);
        echo implode('', $img);
    }
    public function execute(Request $request){

//        echo 'main page<br><br>';

        try{

//            $conn_id = ftp_ssl_connect('5.101.201.14');
//            $login = ftp_login($conn_id, 'AmoPoint', 'atWd5kSp');
//
//            $buff = ftp_rawlist($conn_id, '/ftproot/1c_to_amo');
//            ftp_close($conn_id);
//
//            dump(compact('login', 'conn_id', 'buff'));

            die;

            dump(public_path());

//            echo '<a href="https://test.amopoint-dev.ru/mrclnn2021/test" target="_blank">HTTP Referrer Test Page</a>';

            die;


            $res = view('testpdf');
            $pdf = new Pdf($res->render());
            $res = $pdf->saveAs(public_path('img/test.pdf'));
            if(!$res) dump($pdf->getError());
            dd($res);

            dd($res->render());

//            header('Location: /dbg');
//            exit('all good');
//
//
//
//
//            exit();
//
//
//
//            dd($request->user());


//            dd(Auth::user());

//            $res = DB::table('posts')->truncate();
//            dd($res);

            $offset = $request->input('offset');
//            dd($offset);

//            $posts = DB::table('posts')->orderBy('id', 'desc')->limit(10)->offset($offset)->select('file_name')->get();
            $posts = DB::table('posts')->orderBy('id', 'desc')->whereBetween('id', [102247, 102256])->limit(10)->select('file_name')->get();
            $posts = array_map(function($post){ return public_path("img/$post->file_name"); }, $posts->toArray());

//            $posts = array_filter(scandir(public_path('img/tmp')), function($file){return strlen($file) > 2;});
//            $posts = array_map(function($post){ return public_path("img/tmp/$post"); }, $posts);
            $enough = 0.15;

            $FIC = new FastImageCompare();
            $imageMagickComparator = new ComparatorImageMagick(ComparatorImageMagick::METRIC_NCC,[]);
            $imageMagickComparator->registerNormalizer(new NormalizerSquaredSize(16));
            $FIC->registerComparator($imageMagickComparator,IComparable::PASSTHROUGH);
            $this->displayImages($posts);
            echo "<H1>FIRST STEP</H1><BR><BR>";
            $duplicates = $FIC->findDuplicates($posts, $enough);
            dd($duplicates);
            $this->displayImages($duplicates);

            echo "<H1>SECOND STEP</H1><BR><BR>";
            $uniques = $FIC->findUniques($duplicates, $enough);
            $this->displayImages($uniques);

            echo "<H1>THIRD STEP</H1><BR><BR>";
            $chunks = [];
            foreach ($uniques as $unique){
                $chunk = [$unique];
                foreach ($duplicates as $index => $duplicate) {
                    if($FIC->areSimilar($unique, $duplicate, $enough)){
                        $chunk[] = $duplicate;
                        unset($duplicates[$index]);
                    }
                }
                if(empty(array_diff($chunk, $uniques))) continue; //todo это значит что в текущем чанке только уникальные элементы, неск штук, а значит это ошибка
                $chunks[] = $chunk;
            }

            foreach($chunks as $index => $chunk){
                echo "<H1>CHUNK $index</H1><BR><BR>";
                $this->displayImages($chunk);
            }
//




            exit('exit');

            dd($res);


            die;


            $p = 'https://docs.google.com/feeds/download/documents/export/Export?id=19Sus07CDktMhvDwbgL3gSSgG11vHS645SvGvvvAuG8c&exportFormat=docx';

            file_put_contents(storage_path('test/test.docx'), file_get_contents($p));
            echo 'done';
            die;


//            $amoClientID = 31211566;
//
//            $amo = AmoCRMApiClient();




            function getRandomIndex($data, $column = 'ver') {
                $rand = mt_rand(1, array_sum(array_column($data, $column)));
                $cur = $prev = 0;
                for ($i = 0, $count = count($data); $i < $count; ++$i) {
                    $prev += $i != 0 ? $data[$i-1][$column] : 0;
                    $cur += $data[$i][$column];
                    if ($rand > $prev && $rand <= $cur) {
                        return $i;
                    }
                }
                return -1;
            }

            $managers = [
                ['user' => '60_9693102', 'percent' => 60], // вероятность 2/15
                ['user' => '30_9727542', 'percent' => 30], // вероятность 0/15
                ['user' => '10_9723414', 'percent' => 10], // вероятность 1/15
            ];


            $results = [];
            for($iterator = 10; $iterator > 0; $iterator--){
                $i = getRandomIndex($managers, 'percent');
                $results[] = $managers[$i]['user'];
            }

            dd(array_count_values($results));

            die;




//            $queryString = http_build_query([
//                'access_key' => '75c981dfa2032fbd0afaa88e08de2a17',
//                'query' => '55.7564093,37.7049133',
////                'query' => '48.2084,16.3731',
//            ]);
//
//            $ch = curl_init(sprintf('%s?%s', 'http://api.positionstack.com/v1/reverse', $queryString));
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//
//            $json = curl_exec($ch);
//
//            curl_close($ch);
//
//            echo $json;
//            $apiResult = json_decode($json, true);




            die;


            $parsingCategory = DB::table('parsing_categories')->where('status', null)->first();
            dd($parsingCategory);

            $res = Posts::checkExistence([8029432,7981777,7981767,7981752,7981749,7855323,7842923,7819265,7780636,7628314,7471839,7460370,7411238,7400344,7371878,7362936,7348783,7296322,7053847,7053810,6926285,6925897,6922296,6922279,6920593,6705600,6703167,6662757,6635456,6620120,6595711,6579563,6543192,6527343,6518545,6499973,6485454,6449614,6438789,6399904,6375598,6340032]);
            dd($res);

//            $url =  'https://rule34.xxx/index.php?page=post&s=list&pid=0&tags=tang+-anthro+-furry+-femboy+-mud+-cuntboy+-cuntboy_only+-bara+-yaoi+-weight_gain+-tetra_ai+-tentacle+-plump+-girly+-gay+-gay_sex+-midna+-my_little_pony+-venus_body+-bbw+-intersex+-reptile+-overweight+-beaten+-pain+-torture+-transformation+-scat+-muddy+-covered_in_mud+-mud_bath+-feral+-cuntboy_penetrated+-1cuntboy+-dickgirl+-death+-blood+-fat+-fart+-male_only+-futanari+-futa_on_female+-1futa+-traditional_media_(artwork)+-bursting_breasts+-enormous_breasts+-gigantic_breasts+-hyper_breasts+-hyper+-shitting+-defecating+-pee+-peeing+-cgi+-pregnant_futa+-pregnant_sex+-pregnant+-pregnancy+-peeing_in_mouth+-peeing_self+-fur+-zoophilia';
//            $url = 'https://rule34.xxx/index.php?page=post&s=view&id=7920779';
//
//            $h = [
////                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
////                'Accept-Encoding: gzip, deflate, br',
////                'Accept-Language: ru',
////                'Cache-Control: no-cache',
////                'Pragma: no-cache',
////                'Cookie: webmad_tl=1686386800; __cf_bm=Pt4KRzFX168O.xjRJBZTmvAbDDff9JljDBUL4IbGi74-1686387253-0-AT/YoXhDLZPGYxax0veA8JpRiPLgK6aGS3KX7Mk5efyI4VigxmflPUFdjtG74yplhg==',
////            'Connection: close',
//                'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36',
//            ];
//            $context = stream_context_create(array(
//                'http' => array(
//                    'method' => 'GET',
//                    'header' => $h,
////                'ignore_errors' => true,
//                ),
//                'ssl' => array(
//                    'verify_peer' => false,
//                    'verify_peer_name' => false,
//                    'method' => 'GET',
//                    'header' => $h,
////                'ignore_errors' => true,
//                ),
//            ));
//
//            echo file_get_contents($url, true, $context);
//            die;

//            $source = 'rule34';
//            $category = 'combos-n-doodles';
//
//            $parserConfig = new ParserJobConfig($source, $category);
//            $this->dispatch(new ParserJobNew($parserConfig));
//            exit("$category from $source dispatched!");




//            $api = new bbgClientApi("RepkoIV@ferrostroy.ru", "Evraz12345");

//            $result = $api->call('tenderKeywords', [
//                'keyword' => 'Строительство завода световозвращающих',
//                'search_type' => 'exact',
//                'search_list' => 'tender_all',
////                'price_start' => '10000',
////                'price_stop' => '12000',
////                'regions' => ['москва'],
////                'search_object' => ['title'],
////                'tender_type' => 'all',
//                'order_type' => 'create_date',
//                'order_direction' => 'desc',
//                'offset' => 0,
//                'limit' => 50,
//            ]);

//$api->call('projects', [
//    'years' => [2020],
//    'start_years' => [2020],
//    'order_direction' => 'desc',
//    'offset' => 0,
//    'limit' => 1,
//]);
//
//            dd($result);



//            $dataInput = file_get_contents('php://input');
//            dd($dataInput);
//
//
//            $path = storage_path() . '/1.pdf';
//            echo filesize($path) . '<br>';
//            $path = storage_path() . '/2.pdf';
//            echo filesize($path). '<br>';
//            $path = storage_path() . '/3.pdf';
//            echo filesize($path). '<br>';
            die;


//            $path = public_path('img/1/sample_14d7c115818aebb9a5f9896b7257e7f3.jpg');
////            dd(Helper::clearDirPath($path));
//            $img = new Imagick($path);
//            $img->setImageProperty('test', 'value');
//            $img->writeImage($path);
//            $img2 = new Imagick($path);
//            $res = $img2->getImageProperties();
//            dd($res);
//
//            $p = new Phar($path);
//
//            dd($p);




        } catch (Throwable $e){
            echo $e->getMessage() . ' at line ' . $e->getLine() . ' at file ' . $e->getFile();
        }







//        echo gettype($page);




        die;
        $query = <<<QUERY
select
    tags_metadata,
    tags_artist,
    tags_character,
    tags_copyright,
    tags_general
from posts
QUERY;

        $allTags = DB::select($query);
//        var_dump($allTags);
        $final = [
            'metadata' => [],
            'artist' => [],
            'character' => [],
            'copyright' => [],
            'general' => []
        ];
        foreach ($allTags as $tag) {
            $metaTags = explode(' ', $tag->tags_metadata);
            foreach($metaTags as $metaTag){
                if(!in_array($metaTag, $final['metadata'])){
                    $final['metadata'][] = $metaTag;
                }
            }
            $artistTags = explode(' ', $tag->tags_artist);
            foreach($artistTags as $artistTag){
                if(!in_array($artistTag, $final['artist'])){
                    $final['artist'][] = $artistTag;
                }
            }
            $charTags = explode(' ', $tag->tags_character);
            foreach($charTags as $charTag){
                if(!in_array($charTag, $final['character'])){
                    $final['character'][] = $charTag;
                }
            }
            $copyTags = explode(' ', $tag->tags_copyright);
            foreach($copyTags as $copyTag){
                if(!in_array($copyTag, $final['copyright'])){
                    $final['copyright'][] = $copyTag;
                }
            }
            $genTags = explode(' ', $tag->tags_general);
            foreach($genTags as $genTag){
                if(!in_array($genTag, $final['general'])){
                    $final['general'][] = $genTag;
                }
            }
        }
//        dd($final);
//
        foreach($final as $tagCategory => $tags){
            foreach($tags as $tag){
                $query = <<<UPDATE
insert into
tags
(type, tag)
values  (?, ?)
UPDATE;

                $res = DB::select($query, [$tagCategory, $tag]);
                var_dump($res);
                echo '<br><br><br>';
            }
        }
        die;
        $this->setLogger();
        try{
//            require_once 'vendor/autoload.php';
//            dispatch(new BXUpdaterJob());
//            $curlClient = new Client([
//                                                    'handler' => new HandlerStack(new CurlHandler()),
////                                                    'debug' => true,
//                                                ]);
//            $curlMultiClient = new Client([
//                     'handler' => new HandlerStack(new CurlMultiHandler()),
////                     'debug' => true,
//                 ]);
            try {
//                $curlClient->request('get', 'www.google.com');
//                echo "\n\n-------------------------------------------\n\n";
//                $curlClient->request('get', 'www.google.com');
//                echo "\n\n-------------------------------------------\n\n";
//                $curlMultiClient->request('get', 'www.google.com');
//                echo "\n\n-------------------------------------------\n\n";
//                $curlMultiClient->request('get', 'www.google.com');


//                $links = [
//                    'https://cars.av.by/mercedes-benz/e-klass/100276154',
//                    'https://cars.av.by/dodge/challenger/100439704',
//                    'https://cars.av.by/chrysler/pacifica/100443803',
//                    'https://cars.av.by/ford/mustang/100427701',
//                ];
//
//                $ch = curl_init();
//
//                foreach ($links as $link) {
//                    $timeBeg = microtime(true);
//                    curl_setopt_array($ch, array(
//                        CURLOPT_URL => $link,
////                    CURLOPT_VERBOSE => True,
//                        CURLOPT_RETURNTRANSFER => True,
//                    ));
//                    $resp = curl_exec($ch);
//                    echo 'length : '.strlen($resp).'<br>';
//                    echo sprintf('time receive doc %s', round(microtime(true) - $timeBeg,4)) . '<br>';
//
//                }
//                    dispatch(new AVBYtmpJob());
//                phpinfo();
//                $res = DB::select('SELECT * FROM avby WHERE average_cost IS NOT NULL');
//                $d  = [];
//                foreach($res as $model){
//                    $d[$model->name] = $model->average_cost;
//                }
//                echo json_encode($d);

//                $this->dispatch(new BXUpdaterJob());

//                $this->doc = new HtmlWeb();
//                $p = $this->doc->load('https://cars.av.by/filter?brands[0][brand]=683&brands[0][model]=789&brands[0][generation]=4661');
//                $priceContainers = $p->find('div.listing-item__priceusd');
////                var_dump($r);
//                foreach($priceContainers as $priceContainer){
//                    echo preg_replace('/\D/','', $priceContainer->innertext) . '<br>';
//                }




//                    phpinfo();

//                $this->dispatch(new SiteParser());
//
//                echo 'done';

//                curl_close($ch);

//                $timeBeg = microtime(true);
//                $res1 = $curlClient->request('get', 'https://cars.av.by/mercedes-benz/e-klass/100276154');
//                echo sprintf('time receive doc %s', round(microtime(true) - $timeBeg,4)) . '<br>';
//                $timeBeg = microtime(true);
//                $res2 = $curlClient->request('get', 'https://cars.av.by/dodge/challenger/100439704');
//                echo sprintf('time receive doc %s', round(microtime(true) - $timeBeg,4)) . '<br>';
//                $timeBeg = microtime(true);
//                $res3 = $curlClient->request('get', 'https://cars.av.by/chrysler/pacifica/100443803');
//                echo sprintf('time receive doc %s', round(microtime(true) - $timeBeg,4)) . '<br>';
//                $timeBeg = microtime(true);
//                $res4 = $curlClient->request('get', 'https://cars.av.by/ford/mustang/100427701');
//                echo sprintf('time receive doc %s', round(microtime(true) - $timeBeg,4)) . '<br>';

//                var_dump(strlen($res1->getBody()->getContents()));
//                var_dump(strlen($res2->getBody()->getContents()));
//                var_dump(strlen($res3->getBody()->getContents()));
//                var_dump(strlen($res4->getBody()->getContents()));
//                var_dump(strpos(substr($res1->getBody()->getContents(), 30000, 6000), '<div class="gallery__status">'));
//                var_dump(strpos($res2->getBody()->getContents(), '<div class="gallery__status">'));
//                var_dump(strpos($res3->getBody()->getContents(), '<div class="gallery__status">'));
//                var_dump(strpos($res4->getBody()->getContents(), '<div class="gallery__status">'));
            } catch (ConnectException $e) {
                echo 'Message:' . $e->getMessage();
            }
            echo 'df';
//            echo '<h1>here</h1>';
//            echo '<a href="https://confidence.by?utm_dfs=dsf">confidence</a>';
//
//            $tgs = DB::select('select tag from tags where enabled = 1');
//            $tags = [];
//            foreach($tgs as $tag){
//                $tags[] = $tag->tag;
//            }
//            $whereCopy = implode('%\' or tags_copyright like \'%', $tags);
//            $whereChar = implode('%\' or tags_character like \'%', $tags);
//            $filter = 'WHERE tags_copyright like \'%'.$whereCopy.'%\' or tags_character like \'%'.$whereChar.'%\'';
//
//            war
            die;

//            $this->logger->debug('here');
//            $this->doc = new HtmlWeb();
//            $allLinks = ['https://remval.by/', 'https://remval.by'];
//            $this->getAllPageLinks('https://remval.by/', $allLinks);
//            var_dump($allLinks);

//            dispatch(new TestJob());
//            echo 'dsf';
//            $this->doc = new HtmlWeb();
//            $url = 'https://remval.by/products/raspylyaemye-keramicheskie-zashhitnye-pokrytiya-repacoat-v/';
//            $page = $this->doc->load($url);
//            $title = $page->find('title', 0)->innertext;
//            $desc = $page->find('meta[name=description]', 0)->content;
//            var_dump($desc);





        } catch(Throwable $e){
            echo '<br>';
            echo $e->getMessage() . ' in file ' . $e->getFile() . ' at line ' . $e->getLine();
        }




        die;



        $products = GalleryPosts::all();
        $categories = Categories::all();
        $productsTypes = Product_types::all();

        $currentCategory = $request->category ?: '';
        $currentType = $request->type ?: '';

        return view('main', compact('products', 'categories', 'productsTypes', 'currentCategory', 'currentType'));
    }

    private function getAllPageLinks(string $url, array &$existingLinks) : bool
    {
        $this->logger->debug('checking '.$url);
        $this->logger->debug('allCount : '.count($existingLinks));
        $page = $this->doc->load($url);
        if(!$page){
            echo 'not found page ' . $url;
            $this->logger->error('not found page '.$url);
            return false;
        }
        foreach($page->find('a') as $link){
//            $newLinks = true;
            if(in_array($link->href, $existingLinks)) continue;
            if(strpos($link->href, '://remval.by/') === false) continue;
            if(preg_match('/\.[a-z]+$/', $link->href)) continue;
            $existingLinks[] = $link->href;
            $this->getAllPageLinks($link->href, $existingLinks);
        }
        return true;
    }

    private function getArtistCount(string $tag) : ?int
    {
        $url_tag = str_replace(['+', '\''],['%2b', '&#039;'], $tag);
        $url = 'test/autocomplete.php?q=' . $url_tag;
        $this->logger->info('ARTIST COUNT RUN...');
        $doc = new HtmlWeb();
        $json = $doc->load($url);
        $res = json_decode($json);
        var_dump($res);
        foreach ($res as $author){
            if(preg_match('/^\(\d+\)$/',trim(str_replace($tag, '', $author->label)))){
                if(count($res) >= 1){
                    $count = str_replace([$tag, '(', ')'], '',$author->label);
                    $this->logger->debug($tag . ' : ' . $count);
                    return (int)$count;
                }
                if(count($res) === 0) {
                    $this->logger->warning('not found tag ' . $tag);
                    return null;
                }
                $this->logger->warning('get less than 1 result for '.$tag.':', $res);
                return null;
            }
        }
        $this->logger->warning('not found tag ' . $tag . ' in answer json: ' . $json);
        return null;
    }

    private function downloadCategory(string $cat, $limit = null){
        if(!$cat) return;
        $cats = explode(' ', $cat);
        foreach ($cats as $category){
            $cat = DB::select('SELECT id FROM categories WHERE tag = ?', [$category]);
            if(empty($cat)){
                dispatch(new ParserJob($category, (int)$limit));
                echo '<br>';
                $message = $category .' dispatched!';
                $message .= $limit ? ' with limit ' . $limit : '';
                echo $message;
            } else {
                echo '<br>';
                echo 'category '. $category .' already exist!';
            }

        }


    }

    private function register(array $result){
        foreach ($result as $lead){
            if(!in_array($lead->UF_CRM_1611743664675, $this->filters)){
                echo $lead->UF_CRM_1611743664675;
                echo '<br>';
                $this->logger->info($lead->UF_CRM_1611743664675 . ' is new. write to file.');
                $this->filters[] = $lead->UF_CRM_1611743664675;
                file_put_contents('tmp.log', $lead->UF_CRM_1611743664675 . PHP_EOL, FILE_APPEND);
            }
        }

    }

    private function testBX(int $start = 0, array $filter = [])
    {
        $method = 'crm.lead.list';
//        $method = 'crm.contact.delete';
        $uri = 'https://b24-il9uen.bitrix24.by/rest/1/pygj6lau7vixoam1/'.$method.'.json';
//        $uri = 'https://okko.bitrix24.by/rest/23/bspwvqmlrdmmzqcr/crm.lead.userfield.list.json';

        $queryArray = [
            'start' => $start,
            'order' => [
                'DATE_CREATE' => 'DESC'
            ],
            'select' => ['UF_CRM_1611743664675'],
            'filter' => [
                '!UF_CRM_1611743664675' => $filter,

            ]
        ];
        $client = new Client();
        $queryString = preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($queryArray));
        try{
            $response = $client->request(
                'POST',
                $uri,
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'body' => $queryString
                ]
            );
        } catch(GuzzleException $e){
//            $this->logger->error($e->getMessage());
            var_dump($e->getMessage());
            return null;
        }

        if($response->getStatusCode() === 200){
            $answer = json_decode($response->getBody()->getContents(), false);
//            var_dump($answer->next);
            return $answer;
        }

//        $this->logger->info('Unable to create lead. Response code : ' . $response->getStatusCode(), $queryArray);
        return null;

    }

    private function setSizes(){
            $answer = DB::select('SELECT
    id, file_name

FROM posts
WHERE size = 0
LIMIT 2000');
        var_dump(count($answer));
//        die;
        try{
            foreach ($answer as $post){
                try{
                    $inf = getimagesize(public_path('/img/'.$post->file_name));
                } catch (Throwable $e){
                    echo $post->file_name;
                    echo $e->getMessage();
                    echo '<br>';
                    continue;
                }

                $answer = DB::table('posts')->where('id', $post->id)->update(['width' => $inf[0], 'height' => $inf[1]]);
                if(!$answer){
                    echo 'enable to write to db w: ' . $inf[0] . ' h: ' .$inf[1]. ' file: '.$post->file_name.' id: '.$post->id;
                    echo '<br>';
                }

            }
        } catch (Throwable $e){
            echo '<br><br><br>';
            echo $e->getMessage();
            echo '<br><br><br>';
        }

        echo 'end';
    }

    private function delete_categories(string $categories){
        if(!$categories) return;
        $cats = explode(' ', $categories);
        foreach ($cats as $cat){
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/Minsk'));
            $dateString = $date->format('Y-m-d H:i:s');

            $catDB = DB::select('SELECT * FROM categories WHERE tag = ?', [$cat]);
//            dd($catDB);
            $dir_path = public_path('/img/'.$catDB[0]->dir_name);
            if(file_exists($dir_path)){
                array_map('unlink', glob("$dir_path/*.*"));
                $success = rmdir($dir_path);
                if($success){
                    $affectedCat = DB::table('categories')->where('id', $catDB[0]->id)->update(['deleted_at' => $dateString]);
                    $affectedPosts = DB::table('posts')->where('category_id', $catDB[0]->id)->delete();
                    if($affectedCat === 1){
                        echo '<br>';
                        echo 'category ' . $cat . ' deleted successfully ' . $affectedPosts . ' posts.';
                    }
                } else {
                    echo '<br>';
                    echo 'failed to delete directory ' . $dir_path;
                }

            } else {

                if($catDB[0]->deleted_at){
                    echo '<br>';
                    echo 'category '. $cat .' already deleted';
                } else {
                    echo '<br>';
                    echo 'not found directory ' . $dir_path;
                }

            }

        }

    }

    private function get_artist_info(string $artists){
        if(!$artists) return;
        $this->logger->info('ARTIST INFO RUN...');
        $artists_list = explode(' ', $artists);
        $this->doc = new HtmlWeb();
        $all_count = 0;
        foreach($artists_list as $artist){
            $url = 'test/autocomplete.php?q=' . $artist;
            $json = $this->doc->load($url);
            $res = json_decode($json);
            if(count($res) >= 1){
                $count = str_replace([$artist, '(', ')'], '',$res[0]->label);
                $all_count += (int)$count;
                $this->logger->debug($artist . ' : ' . $count);
            } else if(count($res) === 0) {
                $this->logger->warning('not found tag ' . $artist);
            } else {
                $this->logger->warning('get less than 1 result for '.$artist.':', $res);
            }
        }
        $this->logger->info('TOTAL : ' . $all_count);
    }

    private function file_get_contents_curl( $url ) {

        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_AUTOREFERER, TRUE );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );

        $data = curl_exec( $ch );
        curl_close( $ch );

        return $data;

    }

    private function setLogger(){
        $this->logger = new Logger(
            'main_debug', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/main_debug.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }

    private function getTagAlias(string $tag) : string
    {
        preg_match_all('/[a-zA-Z0-9]*/', $tag, $res);
//        var_dump($res);
        $prefix = implode('', $res[0]);
        return $prefix . '_' . hash('md5', $tag);
    }

    private function setTags(){
        $tags = DB::select('SELECT author FROM needle_authors WHERE processed != 0');
        foreach ($tags as $i => $tag){
            if($i <= 899) continue;
            DB::select('UPDATE posts SET debug = 1 WHERE category_id = 40 AND LOCATE(?, tags_artist) != 0', [$tag->author]);
            $this->logger->debug(sprintf('successfully updated %s/%s', $i, count($tags)));
        }
    }

    private function setHashes(int $id){
        $posts = DB::select('SELECT file_name, id FROM posts WHERE category_id = ?', [$id]);
        $ih = new ImageHash();
        foreach($posts as $post){
            $hash = $ih->createHashFromFile(public_path('/img/'.$post->file_name));
            $affected = DB::table('posts')->where('id', $post->id)->update(['hash' => $hash]);
        }
        var_dump($affected);
//        $hash =
//
    }

    private function insertRecordToHNA(string $tags_artist, int $estimate) : array
    {
        if($tags_artist === ''){
            return ['success' => true, 'message' => 'Empty tags_artist received.'];
        }
        $message = [];
        foreach (explode(' ', $tags_artist) as $tag){
            $info = 'tag ' . $tag;
            $isNew = !DB::select(')', [$tag]);
            $info .= $isNew ? ' is new.' : ' already exist.';
            if(!$isNew){
                $message[] = ['success' => true, 'message' => $info];
                continue;
            }
            if($estimate === 0){
                $processed = 3;
            } elseif($estimate === 2){
                $processed = 0;
                $this->dispatch(new ParserJob($tag, 16));
            }
            // записываем в hna полученный тег
            $success = DB::table('needle_authors')->insert(
                ['author' => $tag, 'author_alias' => $this->getTagAlias($tag), 'processed' => $processed]);
            if($processed === 0){
                $info .= $success ? ' added successfully. ' : ' failed to add. ';
            } else {
                $info .= $success ? ' rejected successfully. ' : ' failed to reject. ';
            }
            // отмечаем все посты с таким тегом
            DB::select('UPDATE posts SET debug = 1 WHERE category_id = 40 AND LOCATE(?, tags_artist) != 0', [$tag]);
            $message[] = $info;
        }
        return ['success' => true, 'message' => $message];
    }

    private function find_dupl(int $cat_id){
        $all_posts = DB::select(' ORDER BY tags_character DESC, hash', [$cat_id]);
        $characters = [];
        $ih = new ImageHash();
        foreach ($all_posts as $post){
            if(!isset($characters[$post->tags_character])) $characters[$post->tags_character] = [];
            $characters[$post->tags_character][] = [
                'file' => $post->file_name,
                'hash' => $ih->createHashFromFile(public_path('/img/'.$post->file_name), 15, 6, true),
//                'hash' => $post->hash,
                'id' => $post->id
            ];
        }
//        dd($characters);

        $duplicates = [];
        $limit = 0;
        $processed = [];
        foreach ($characters as $char){
            $limit++;
            if($limit > 60) break;
            foreach ($char as $post){
                if(in_array($post['id'], $processed)) continue;
                $processed[] = $post['id'];
                $dupl = [$post['id'] => $post['file']];
                foreach ($char as $_post){
                    if(in_array($_post['id'], $processed)) continue;
                    if($ih->compareImageHashes($post['hash'], $_post['hash'], 0.25)){
                        $dupl[$_post['id']] = $_post['file'];
                        $processed[] = $_post['id'];
                    }
                }
                if(count($dupl) > 1) $duplicates[] = $dupl;
            }
        }
//        dd($characters);
        return ['all' => $all_posts, 'dupl' => $duplicates];
    }
}
