<?php

namespace App\Http\Controllers;

use App\Logger;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Longman\TelegramBot\Telegram;

use pepeEpe\FastImageCompare\ComparatorImageMagick;
use pepeEpe\FastImageCompare\FastImageCompare;
use pepeEpe\FastImageCompare\IComparable;
use pepeEpe\FastImageCompare\NormalizerSquaredSize;
use function morphos\Russian\inflectName;

class dbg extends Controller
{

    use Logger;
    public function __construct()
    {
        $this->middleware('auth');
        $this->setLogger();
    }
    private function displayImages(array $res)
    {
        $img = array_map(function($src){ return "<img alt='' style='width: 20%' src='$src'>"; }, $res);
        echo implode('', $img);
    }

    private function duplicatesTest($offset = 0)
    {

        try{
            $res = $path = DB::table('posts')->where('status', 2)->orderByRaw("RAND()")->first()->file_name ?? null;
            dd($res);
//            $posts = DB::table('posts')->orderBy('id', 'desc')->limit(10)->offset($offset)->select(['file_name', 'post_id'])->get();
            $posts = DB::table('posts')->orderBy('id', 'desc')->whereBetween('id', [102247, 102256])->limit(10)->select(['file_name', 'post_id'])->get();
            $pst = [];
            foreach($posts as $post){
                $path = Storage::disk('gallery_posts')->path($post->file_name);
                $src = "/gallery/post/$post->post_id";
                $pst[$src] = $path;
            }
            $posts = $pst;
            $enough = 0.15;

            $FIC = new FastImageCompare();
            $imageMagickComparator = new ComparatorImageMagick(ComparatorImageMagick::METRIC_NCC,[]);
            $imageMagickComparator->registerNormalizer(new NormalizerSquaredSize(16));
            $FIC->registerComparator($imageMagickComparator,IComparable::PASSTHROUGH);
            $this->displayImages(array_keys($posts));
            echo "<H1>FIRST STEP</H1><BR><BR>";
//            dd(array_values($posts));
            $duplicates = $FIC->findDuplicates(array_values($posts), $enough);
//            $duplicates = $FIC->findUniques(array_values($posts), $enough);
            dd($duplicates);
            dd($posts);
            $displ = array_map(function($path) use ($posts){ return array_search($path, $posts); }, $duplicates);
            $this->displayImages($displ);

            echo "<H1>SECOND STEP</H1><BR><BR>";
            $uniques = $FIC->findUniques($duplicates, $enough);
            $displ = array_map(function($path) use ($posts){ return array_search($path, $posts); }, $uniques);
            $this->displayImages($displ);

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
                $displ = array_map(function($path) use ($posts){ return array_search($path, $posts); }, $chunk);
                $this->displayImages($displ);
            }
        } catch (\Throwable $e){
            dd("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }


    }

    public function execute(Request $request)
    {
        try{

            $res = inflectName('Диана', 'родительный');
            dd($res);

            exit('exit');

            $this->duplicatesTest($request->input('offset'));
            exit('<br>exit');

            $path = '742c4c1b766012bf89abf55ba0c43ab1.jpeg';
            $res = $path ? Storage::disk('gallery_posts')->path($path) : public_path('img/no-file.png');
            $exist = Storage::disk('gallery_posts')->exists($path);
            dd($exist);

            exit('exit');

            $res= DB::table('parsing_categories')->insertOrIgnore(['tag' => 'test']);
            dd($res);

            $redirect_uri = 'https://mrlcnn.xyz/google/auth';

            $client = new Client();
            $client->setAuthConfig(storage_path('credit/').env('GOOGLE_API_CREDIT'));
            $client->setRedirectUri($redirect_uri);
            $client->addScope("https://www.googleapis.com/auth/drive");
            $service = new Drive($client);

            $token = $client->getAccessToken();
//            dd($_SESSION ?? null);
//            dd($token);

            // add "?logout" to the URL to remove a token from the session
            if (isset($_REQUEST['logout'])) {
                unset($_SESSION['upload_token']);
            }

            // set the access token as part of the client
            if (!empty($_SESSION['upload_token'])) {
                $client->setAccessToken($_SESSION['upload_token']);
                if ($client->isAccessTokenExpired()) {
                    unset($_SESSION['upload_token']);
                }
            } else {
//                $_SESSION['code_verifier'] = $client->getOAuth2Service()->generateCodeVerifier();
                $authUrl = $client->createAuthUrl();
                echo "<a class='login' href='$authUrl'>Connect Me!</a>";
            }

            /************************************************
             * If we're signed in then lets try to upload our
             * file. For larger files, see fileupload.php.
             ************************************************/
            echo "here";
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && $client->getAccessToken()) {
                echo "inside";
                // We'll setup an empty 1MB file to upload.
                DEFINE("TESTFILE", 'testfile-small.txt');
                if (!file_exists(TESTFILE)) {
                    $fh = fopen(TESTFILE, 'w');
                    fseek($fh, 1024 * 1024);
                    fwrite($fh, "!", 1);
                    fclose($fh);
                }

                // This is uploading a file directly, with no metadata associated.
                $file = new DriveFile();
                $result = $service->files->create(
                    $file,
                    [
                        'data' => file_get_contents(TESTFILE),
                        'mimeType' => 'application/octet-stream',
                        'uploadType' => 'media'
                    ]
                );
                dd($result);

                // Now lets try and send the metadata as well using multipart!
                $file = new DriveFile();
                $file->setName("Hello World!");
                $result2 = $service->files->create(
                    $file,
                    [
                        'data' => file_get_contents(TESTFILE),
                        'mimeType' => 'application/octet-stream',
                        'uploadType' => 'multipart'
                    ]
                );
            }



            exit('exit');
            dd($token ?? null);







            $tg = new Telegram(env('TELEGRAM_API_KEY'), env('TELEGRAM_BOT_NAME'));
            $cl = $tg->setCom();
            dd($cl);


            $res = $tg->deleteWebhook();
            dd($res);
            $res = $tg->setWebhook('https://mrlcnn.xyz/tg/hook');


            var_dump($res->isOk());
            echo $res->getDescription();
            dd($res);



        } catch (\Throwable $e){
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }
    }
}