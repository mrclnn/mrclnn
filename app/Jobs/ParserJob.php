<?php

namespace App\Jobs;

use App\Services\ImageHash;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlWeb;
use Throwable;

class ParserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use LoggerAwareTrait;

    // т.к. некоторые gif могут весить очень много, таймаут 8 минут
    public $timeout = 480;

    private $tag;
    private $tagAlias;
    private $pid;
    private $doc;
    private $dirName;
    private $dirPath;
    private $files_list = [];
    private $lastPage;
    private $limit;
    private $count;
    private $failed;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $tag, $limit = null, $params = [])
    {
        $this->tag = $tag;
        $this->tagAlias = $this->getTagAlias($this->tag);

        //TODO поменять на '0', иначе парсинг всегда будет начинаться с 5 страницы
        $this->pid = $params['pid'] ?? '168';
        //TODO поменять на '0', иначе парсинг всегда будет начинаться с 5 страницы

        $this->lastPage = $params['lastPage'] ?? null;
        $this->count = $params['count'] ?? 0;
        $this->failed = $params['failed'] ?? 0;
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->setLogger();
        if($this->tag === ''){
            $this->logger->warning('Received empty tag. Return.');
            return;
        }
        $cat = DB::select('SELECT id, downloading FROM categories WHERE tag = ?', [$this->tag]);
        if($cat && !$cat[0]->downloading){
            $this->logger->warning(sprintf('Received tag %s. Already exist. id = %s. Return.', $this->tag, $cat[0]->id));
            return;
        }
        $this->doc = new HtmlWeb();
        $this->dirName = ($this->limit === null) ? $this->tagAlias : 'tmp';
        $this->dirPath = public_path() . "/img/$this->dirName/";

        try{
            $isEndWork = $this->parse();
        } catch(Exception $e){
            $this->logger->error($e->getMessage());
        }

        // выполнится перед завершением и если это limit
        if($isEndWork){
            if($this->limit !== null){
                $affected =
                    DB::table('needle_authors')
                        ->where('author', $this->tag)
                        ->update([ 'preloaded' => TRUE]);
                if($affected === 1){
                    $this->logger->info('successfully preloaded in db needle_authors');
                } else {
                    $this->logger->warning('failed preloaded in db needle_authors');
                }
            } else {
                $date = $this->getTimeForDB();
                $affected =
                    DB::table('categories')
                        ->where('tag', $this->tag)
                        ->update(['uploaded_at' => $date, 'updated_at' => $date, 'downloading' => 0]);
                if($affected === 1){
                    $this->logger->info('successfully uploaded_at in categories');
                } else {
                    $this->logger->warning('failed to set uploaded_at in categories');
                }
            }
        }

        // возвращает null при ошибке, необходимо повторить через 60 секунд
        // возвращает false если все ок, продолжаем работу
        // возвращает true если повторять более не требуется, завершаем работу
        // 20 попыток неуспешного исполнения подряд, после чего работа завершается
        if(!$isEndWork){
            if($isEndWork === null){
                $this->failed++;
                if($this->failed >= 20){
                    $this->logger->error('Limit of failed execution reached. End work.');
                    return;
                }
                $delay = 60;
            } else {
                $this->failed = 0;
                $delay = 0;
            }
            self::dispatch($this->tag, $this->limit,
                [
                    'pid' => $this->pid,
                    'lastPage' => $this->lastPage,
                    'count' => $this->count,
                    'failed' => $this->failed
                ])->delay(Carbon::now()->addSeconds($delay));
        }
    }

    private function parse() : ?bool
    {
        $filters = [
            //TODO реализовать подгрузку фильтров
        ];
        $filters = '+' . implode("+", $filters);
        $url_tag = str_replace(['+', '\''],['%2b', '%27'], $this->tag);
        $url = 'test?page=post&s=list&tags=' . $url_tag . $filters . '&pid=' . $this->pid;


        $page = $this->doc->load($url);
        if($page === null){
            $this->logger->warning(sprintf('Unable to load site %s', $url));
            return null;
        }
        $current_page = (int)($this->pid / 42) + 1;
        $pagination = $page->find('div.pagination', 0);
        if(!$pagination){
            $this->logger->warning('Not found pagination: div.pagination, ' . $url);
            return null;
        }

        if($this->lastPage === null){
            $lastPage = $pagination->find('a[alt="last page"]', 0);
            if(!$lastPage){
                $this->lastPage = 1;
            } else {
                $lastPagePid = preg_replace('/.+&pid=/', '', $lastPage->href);
                $this->lastPage = (int)($lastPagePid / 42) + 1;
            }
        }
        $this->logger->info(sprintf('Start parsing %s, page %s/%s', $this->tag, $current_page, $this->lastPage));

        if(!$this->get_contents($page)){
            $this->logger->info(sprintf('Required limit %s/%s reached. End work', $this->count, $this->limit));
            return true;
        }

        $next_page = $pagination->find('a[alt=next]', 0);
        if(!$next_page){
            $this->logger->info(sprintf('This is last page for %s. End work', $this->tag));
            return true;
        }
        $href = $next_page->href;
        $this->pid = substr($href, strpos($href, '&pid=') + 5);
        return false;
    }

    private function get_contents(HtmlDocument $page) : bool
    {
        $continue = true;
        if(!file_exists($this->dirPath)){
            if (!mkdir($this->dirPath) && !is_dir($this->dirPath)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $this->dirPath));
            }
        }
        $success_count = 0;
        $total_count = 0;
        foreach($page->find('span.thumb') as $post){
            sleep(2);
            $timeBegin = microtime(true);
            $total_count++;
            $url = $post->find('a', 0)->href;
            $id = preg_replace('/\D/', '', $url);
            $postUrl = 'test?page=post&s=view&id=' . $id;

            $postHTML = $this->safe_parse($postUrl);
            if(!$postHTML){
                $this->logger->warning(sprintf('not found post page: %s', $postUrl));
                continue;
            }

            $post = new HtmlDocument();
            $post->load($postHTML);


            $src = $post->find('img#image', 0);
            if($src){
                try{
                    $src = !empty($src->attr['data-cfsrc']) ? $src->attr['data-cfsrc'] : $src->attr['src'];
                    if(!$src){
                        $this->logger->warning(sprintf('not found src or cfsrc in page: %s', $postUrl));
                        continue;
                    }
                } catch (Throwable $e){
                    $this->logger->error($e->getMessage());
                    continue;
                }

            } else {
                $this->logger->warning(sprintf('not found #image in page: %s', $postUrl));
                continue;
            }
            $fileInfo = $this->getPost($src);
            if(!$fileInfo) continue;
            $tags = $this->getTags($post, $postUrl);
            $fileInfo['tags'] = $tags;
            $fileInfo['id'] = $id;
            $this->files_list[] = $fileInfo;

            $success_count++;
            $this->logger->info(sprintf('Successfully parsed: %s seconds | %s', microtime(true) - $timeBegin, $src));
            if($this->limit !== null && (int)$this->count + $success_count >= $this->limit){
                $continue = false;
                break;
            }
        }
        $this->logger->info(sprintf('Processed successfully %s/%s images.', $success_count, $total_count));
        $this->count += $success_count;
        if($this->limit === null){
            $this->writeToDB();
        }
        return $continue;
    }

    private function safe_parse(string $url) : string
    {
        $context = stream_context_create(
            array(
                "http" => array(
//                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36",
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36"
                )
            )
        );
        return file_get_contents($url, false, $context);
    }

    private function getTags($page, $url = null) : ?array
    {
        $tags = ['copyright' => '', 'character' => '', 'artist' => '', 'general' => '', 'metadata' => ''];
        $tagSidebar = $page->find('ul#tag-sidebar', 0);
        if(!$tagSidebar){
            $this->logger->warning('not found tag sidebar in: ' . $url);
            return $tags;
        }
        foreach($tagSidebar->children as $tag){
            if($tag->tag === 'li' && isset($tag->attr['class'])){
                $tagType = str_replace(array('tag-type-', ' tag'), '', $tag->attr['class']);
                $tagValue = str_replace(' ', '_', $tag->children[1]->plaintext);
                if(!isset($tags[$tagType])){
                    $this->logger->warning(sprintf('Received unexpected tag-type: %s', $tagType));
                    continue;
                }
                $tags[$tagType] .= $tagValue . ' ';
            }
        }
        foreach($tags as $type => $value){
            $tags[$type] = trim($value);
        }

        return $tags;
    }

    private function getPost(string $src) : ?array
    {
        $fileName = preg_replace('/\?\d+/', '',substr($src, strripos($src, '/') + 1));
        if($this->limit !== null){
            $fileName = $this->tagAlias . '_' . $fileName;
        }
        $filePath = $this->dirPath . $fileName;
        if(file_exists($filePath)){
            $this->logger->warning(sprintf('already exist: %s', $filePath));
            return null;
        }
        $mime = substr($fileName, strripos($fileName, '.') + 1);
        $fileInfo = [];
        $fileInfo['name'] = $fileName;

        try{
            switch($mime){
                case 'jpg' : $img = imagecreatefromjpeg($src); break;
                case 'jpeg' : $img = imagecreatefromjpeg($src); break;
                case 'png' : $img = imagecreatefrompng($src); break;
                case 'gif' :
                    $this->logger->warning('skip gif: ' . $src);
                    return null;
//                    $image = $this->file_get_contents_curl($src);
//                    $success = file_put_contents($filePath, $image);
//                    if($success){
//                        try{
//                            $inf = getimagesize($filePath);
//                            $fileInfo['size'] = $success;
//                            $fileInfo['width'] = $inf[0] ?: 0;
//                            $fileInfo['height'] = $inf[1] ?: 0;
//                        } catch(Throwable $e){
//                            $this->logger->error('Unable to get sizes: '.$e->getMessage());
//                        }
//                        $this->files_list[] = $fileInfo;
//                        return true;
//                    }
//                    break;
                default: $this->logger->warning(sprintf('Unknown mime: %s', $mime)); return null;
            }

            if(imagesx($img) / imagesy($img) < 0.3 OR imagesx($img) / imagesy($img) > 2.5){
                $this->logger->warning(sprintf('unsuitable size %s. skip. %s', imagesx($img) / imagesy($img), $src));
                return null;
            }

            $limitSize = 1000000; // для изображений больше будет применяться один и тот же максимальный уровень сжатия
            $limitQuality = 60; // минимальный уровень качества. чем меньше тем сильнее сжатие
            $compress = floor(((imagesx($img) * imagesy($img)) / $limitSize) * 10) / 10;
            if($compress > 1) $compress = 1;
            $quality = 100 - ((100 - $limitQuality) * $compress);
            $success = imagejpeg($img, $filePath, $quality);
            if($success){
                try{
                    $inf = getimagesize($filePath);
                    $fileInfo['original_uri'] = $src;
                    $fileInfo['size'] = filesize($filePath);
                    $fileInfo['width'] = $inf[0] ?: 0;
                    $fileInfo['height'] = $inf[1] ?: 0;
                } catch(Throwable $e){
                    $this->logger->error('Unable to get sizes: '.$e->getMessage());
                }
                return $fileInfo;
            }
        } catch (Exception $e){
            $this->logger->error('Unable to get img: ' . $e->getMessage());
        }
        return null;
    }

    private function writeToDB() :bool
    {
        try{
            $answer = DB::select('SELECT id FROM categories WHERE tag = ?', [$this->tag]);
            if(empty($answer)){
                DB::table('categories')->insert([
                      'name' => $this->tag,
                      'dir_name' => $this->dirName,
                      'tag' => $this->tag,
                      'tag_alias' => $this->tagAlias,
                      'enabled' => 1,
                      'downloading' => 1,
                      'type' => 1
                  ]);
                $category_id = DB::select('SELECT id FROM categories WHERE tag = ?', [$this->tag])[0]->id;
            } else {
                $category_id = $answer[0]->id;
            }
            $insert = [];
            $uploaded_at = $this->getTimeForDB();
            $ih = new ImageHash();
            foreach($this->files_list as $file_info){
                $str = [];
                $str['category_id'] = $category_id;
                $str['status'] = 1;
                $str['file_name'] = "$this->dirName/{$file_info['name']}";
                $str['width'] = $file_info['width'];
                $str['height'] = $file_info['height'];
                $str['size'] = $file_info['size'];
                $str['original_uri'] = $file_info['original_uri'];
                $str['post_id'] = $file_info['id'];
                $str['tags_copyright'] = $file_info['tags']['copyright'];
                $str['tags_character'] = $file_info['tags']['character'];
                $str['tags_artist'] = $file_info['tags']['artist'];
                $str['tags_general'] = $file_info['tags']['general'];
                $str['tags_metadata'] = $file_info['tags']['metadata'];
                $str['uploaded_at'] = $uploaded_at;
                $str['hash'] = $ih->createHashFromFile(public_path("/img/$this->dirName/{$file_info['name']}"));

                $insert[] = $str;
            }
            DB::table('posts')->insert($insert);
        } catch(Throwable $e){
            $this->logger->error(sprintf('%s:%s %s', $e->getFile(),$e->getLine(),$e->getMessage()));
            return false;
        }
        return true;
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
            'SiteParser', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/parser.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }

    private function getTagAlias(string $tag) : string
    {
        preg_match_all('/[a-zA-Z0-9]*/', $tag, $res);
        $prefix = implode('', $res[0]);
        return $prefix . '_' . hash('md5', $tag);
    }

    private function getTimeForDB() : string
    {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('Europe/Minsk'));
        return $date->format('Y.m.d H:i:s');
    }
}
