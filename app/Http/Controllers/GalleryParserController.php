<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use simplehtmldom\HtmlDocument;
use simplehtmldom\HtmlWeb;

class GalleryParserController extends Controller
{
    private $tags_list = [

    ];
    private int $fileName = 1;
    private HtmlWeb $doc;
    private string $tag;
    private string $dirName;
    public function execute(Request $request){
        $this->doc = new HtmlWeb();
//
//        $res = (Categories::all())->toArray();
//        var_dump($res->toArray());
        $cats = [
//            1 => 'a',
//            2 => 'b',
//            3 => 'c',
//            4 => 'd',
//            5 => 'f',
//            6 => 'g',
            7 => 'h',
            8 => 'l',
            9 => 'm',
            10 => 'n',
            11 => 'o',
            12 => 'p'
        ];


//        foreach ($cats as $id => $cat) {
//            $res = scandir("D:\pr\OpenServer\domains\\test.laravel\public/img/{$cat}/");
//            unset($res[0]);
//            unset($res[1]);
//            foreach($res as $fileName){
//                Posts::create(['category_id' => $id, 'file_name' => "{$cat}/{$fileName}"]);
//            }
//        }

        die;

        $this->tag = 'a';
        $this->dirName = "D:\pr\OpenServer\domains\\test.laravel\public/img/{$this->tag}/";

        $this->parse($this->tag);



    }

    private function getLinks($tag, $pid){

        $url = 'test/index.php?page=post&s=list&tags=' . $tag . '&pid=' . $pid;
        $page = $this->doc->load($url);
        if($page !== null){
            $links = '';
            foreach($page->find('span.thumb') as $post){

                $url = $post->find('a', 0)->href;
                $id = preg_replace('/\D/', '', $url);
                $post = $this->doc->load('test/index.php?page=post&s=view&id=' . $id);
                $src = $post->find('img#image', 0);
                if($src){
                    $src = $src->attr['data-cfsrc'];
                } else {
//                var_dump('deleted post');
                    continue;
                }
                $links .= $src . ' ';
            }
        }
        return (object)[
            'links' => trim($links) ?: null,
            'pid' => $this->getPid($page)
        ];
    }

    private function getPid($page):string
    {
        $pagination = $page->find('div.pagination', 0);
        $next_page = $pagination->find('a[alt=next]', 0);
        if(empty($next_page)){
            return '-1';
        }
        $href = $next_page->href;
        return substr($href, strpos($href, '&pid='));
}

    private function parse(string $tag, string $pid = '0'){
        //        echo 'GALLERY PARSER';

        $url = 'test/index.php?page=post&s=list&tags=' . $tag . '&pid=' . $pid;
        $i = 0;
        do{
            $page = $this->doc->load($url);
            if($page === null){
                echo 'не удалось загрузить сайт';
            }

            $this->get_contents($page);


            $pagination = $page->find('div.pagination', 0);
            $next_page = $pagination->find('a[alt=next]', 0);
            if(empty($next_page)){
                echo 'not found next page';
                break;
            }
            $href = $next_page->href;
            $pid = substr($href, strpos($href, '&pid='));
            $url = preg_replace('/&pid=\d+/', $pid, $url);
            $i++;
        }while($i < 50);

        echo 'END';
    }

    private function get_contents(HtmlDocument $page){

//        $dirName = storage_path()."/gallery_parser/{$this->tag}/";
        var_dump($this->dirName);
        if(!file_exists($this->dirName)){
            if (!mkdir($this->dirName) && !is_dir($this->dirName)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->dirName));
            }
        }

        foreach($page->find('span.thumb') as $post){

            $url = $post->find('a', 0)->href;
            $id = preg_replace('/\D/', '', $url);
//            var_dump($id);
            $post = $this->doc->load('test/index.php?page=post&s=view&id=' . $id);
            $src = $post->find('img#image', 0);
            if($src){
                $src = $src->attr['data-cfsrc'];
            } else {
//                var_dump('deleted post');
                continue;
            }
//            echo $src . ',';
            $fileName = $this->dirName . $this->fileName++ . '.jpg';

            if(file_exists($fileName)){
                echo 'already exists' . '<br>';
                continue;
            }

            $image = $this->file_get_contents_curl($src);
            $success = file_put_contents($fileName, $image);

            var_dump(!!$success);

        }

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

    private function download(string $tag)
    {

    }

}
