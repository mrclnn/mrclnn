<?php


namespace App\Http\Controllers;


use App\Models\Categories;
use App\Services\ImageHash;
use Illuminate\Support\Facades\DB;
use Throwable;

class DuplicatesController extends Controller
{
    public function execute(){
        // получить список авторов
        // разделить все работы автора на персонажей
        try{
            $posts = Categories::getFromTag('leukocrisp')->getAllPosts();
            $characters = [];
            while($post = $posts->pop()){
                foreach($post->getTags() as $tag){
                    if($tag->type !== 'character') continue;
                    if(!isset($characters[$tag->tag])) $characters[$tag->tag] = [];
                    $characters[$tag->tag][] = $post;
                    break;
                }
            };
            $characters = array_filter($characters, function($character){
                return (count($character) > 1);
            });

            return view('duplicates', ['characters' => $characters]);

        } catch (Throwable $e){
            dd($e);
        }

//        return view('duplicates')
    }
    private function duplicatesQuery(){






        $all_posts = [];
        $category_name = '';
        $category_id = 0;
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


        if($duplicates === []){
            $affected = DB::table('categories')->where('id', $category_id)->update(['rank' => 9]);
            return $this->duplicatesQuery();
        }
//        return ['success' => true, 'blabla' => 'here'];
//        $this->eventStreamMessage('end');
        return ['success' => true, 'env' => ['tag_name' => $category_name, 'tag_id' =>$category_id, 'dupl' => $duplicates]];
    }

//    private function eventStreamMessage(string $action, string $content = '', string $progress = ''){
//        echo "data: {'action':$action, 'content':$content, 'progress':$progress}";
//        echo "\n\n";
//        ob_end_flush();
//        flush();
//    }
}