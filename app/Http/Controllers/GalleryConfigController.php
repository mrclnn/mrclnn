<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use simplehtmldom\HtmlWeb;
use Throwable;

class GalleryConfigController extends Controller
{
    private $request;

    public function execute(Request $request){
        return 'sorry';

        try{
            $this->request = $request->all();
            if(isset($this->request['action'])){
                switch ($this->request['action']){
                    case 'load':
//                        $env = $this->loadCategories();
//                        dd($env);
                        return view('gallery_config_load');
                    case 'duplicates':
                        return $this->searchDuplicates();
                        break;
                }
            }
            var_dump($this->request['action']);
            echo 'empty get parameter. try action=load or action=duplicate';
        } catch (Throwable $e){
            echo $e->getMessage() . ' at file ' . $e->getFile() . ' at line ' . $e->getLine();
        }



    }

    private function searchDuplicates()
    {
//        $posts = DB::select('
//SELECT file_name, tag, hp.id, category_id
//FROM posts AS hp
//JOIN
//(SELECT id, tag FROM categories
//WHERE deleted_at IS NULL and enabled = 1 AND type = 1 AND rank = 0
//ORDER BY id
//LIMIT 1 offset 1) AS category
//WHERE hp.category_id = category.id AND hp.status != 3
//ORDER BY tags_character desc, hash desc
//LIMIT 50
//OFFSET ?
//', [0]);
//        $posts = DB::select('SELECT file_name, \'slugbox\' as tag, id, 50 as category_id FROM posts WHERE category_id = 50 ORDER BY hash');
//        $env = [];
//        foreach($posts as $post){
//            if(!isset($env[$post->category_id])) $env[$post->category_id] = ['name' => $post->tag, 'posts' => []];
//            $env[$post->category_id]['posts'][] = (object)['id' => $post->id, 'src' => $post->file_name];
//        }
//        dd($env);
        return view('gallery_config');
    }
}
