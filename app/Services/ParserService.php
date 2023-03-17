<?php


namespace App\Services;


use Illuminate\Support\Facades\DB;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use RuntimeException;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlWeb;
use Throwable;

class ParserService
{
    use LoggerAwareTrait;
    private $dirName;
    private $doc;
    private $filesList;
    public function __construct(string $dirName, LoggerInterface $logger = null)
    {
        $this->doc = new HtmlWeb();
        $this->setLogger($logger ?: $this->getDefaultLogger());
        $this->dirName = $dirName;
    }

    private function get_contents(HtmlDocument $page){

        if(!file_exists($this->dirName)){
            if (!mkdir($this->dirName) && !is_dir($this->dirName)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $this->dirName));
            }
        }
        $success_count = 0;
        $total_count = 0;
        $filesList = [];
        foreach($page->find('span.thumb') as $post){
            $timeBegin = microtime(true);
            $total_count++;
            $url = $post->find('a', 0)->href;
            $id = preg_replace('/\D/', '', $url);
            $postUrl = 'test.php?page=post&s=view&id=' . $id;
            $post = $this->doc->load($postUrl);
            if(!$post){
                $this->logger->warning(sprintf('not found post page: %s', $postUrl));
                continue;
            }

            $src = $post->find('img#image', 0);
            if($src){
                $src = $src->attr['data-cfsrc'] ?: $src->attr['src'];
                if(!$src){
                    $this->logger->warning(sprintf('not found src or cfsrc in page: %s', $postUrl));
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
            $filesList[] = $fileInfo;

            $success_count++;
//            $fileName = preg_replace('/\?\d+/', '',substr($src, strripos($src, '/') + 1));
            $this->logger->info(sprintf('Successfully parsed: %s seconds | %s', microtime(true) - $timeBegin, $src));

        }
        $this->logger->info(sprintf('Processed successfully %s/%s images.', $success_count, $total_count));
        $this->writeToDB($filesList);
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
                $tagValue = str_replace(' ', '_', $tag->children[0]->plaintext);
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
        $filePath = $this->dirName . $fileName;
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
        } catch (Throwable $e){
            $this->logger->error('Unable to get img: ' . $e->getMessage());
        }
        return null;
    }

    private function writeToDB(){

        $answer = DB::select('.$this->tag.');
        if(empty($answer)){
            DB::table('categories')->insert([
                                                      'name' => $this->tag,
                                                      'dir_name' => $this->tag,
                                                      'tag' => $this->tag,
                                                      'enabled' => 1
                                                  ]);
            $category_id = DB::select('SELECT id FROM categories WHERE tag = \''.$this->tag.'\'')[0]->id;
        } else {
            $category_id = $answer[0]->id;
        }
        $insert = [];
        $uploaded_at = $this->getTimeForDB();
        foreach($this->files_list as $file_info){
            $str = [];
            $str['category_id'] = $category_id;
            $str['status'] = 1;
            $str['file_name'] = "{$this->tag}/{$file_info['name']}";
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

            $insert[] = $str;
        }
        DB::table('posts')->insert($insert);
    }

    private function getDefaultLogger(){
        return new Logger(
            'parserDefault', [

            new PsrHandler(app()->make('log'), Logger::WARNING),
            new RotatingFileHandler(storage_path('logs/parserDefault.log'), 14, Logger::DEBUG, true, 0664),

        ], [
                new PsrLogMessageProcessor(),
            ]
        );
    }
}