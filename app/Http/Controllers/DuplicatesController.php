<?php


namespace App\Http\Controllers;


use App\Services\ImageHash;
use Illuminate\Support\Facades\DB;

class DuplicatesController extends Controller
{
    public function execute(){
        header('Cache-Control: no-cache');
        header("Content-Type: text/event-stream\n\n");

        $this->duplicatesQuery();
//        $t = 3;
//        while ($t-- > 0) {
//            // Every second, send a "ping" event.
//
////    echo "event: ping\n";
//
//            echo 'data: {"time": "'.$t.'"}';
//            echo "\n\n";
//
//            // Send a simple message at random intervals.
//
//
////            ob_end_flush();
////            flush();
////            sleep(1);
//        }
//        echo 'data: end';
//        echo "\n\n";
//        ob_end_flush();
//        flush();
    }
    private function duplicatesQuery(){
        $all_posts = [];
        $category_name = '';
        $category_id = 0;
        while($all_posts === []){
            $category = DB::select('SELECT id, name FROM categories hc WHERE hc.rank = 0 and hc.type = 1 ORDER BY id LIMIT 1');
            $category_id = $category[0]->id;
            $category_name = $category[0]->name;
            $all_posts = DB::select(' AND hp.category_id = ?
ORDER BY tags_character DESC, hash
        \'', [$category_id]);

            if($all_posts === []){
                $affected = DB::table('categories')->where('id', $category_id)->update(['rank' => 9]);
            }
        }
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

        $duplicates = [];
        $limit = 0;
        $processed = [];
        $progress = 0;
        foreach ($characters as $charTag => $char){
            $limit++;
            $progress++;
            if($limit > 60) break;
            foreach ($char as $post){
                if(in_array($post['id'], $processed)) continue;
                $processed[] = $post['id'];
                $dupl = [$post['id'] => ['src' => $post['file']]];
                foreach ($char as $_post){
                    $action = 'checking';
                    $message = "checking character $charTag";
//                    $progress = $progress.'/'.count($characters);
                    $this->eventStreamMessage($action, $message, $progress);
                    if(in_array($_post['id'], $processed)) continue;
                    if($ih->compareImageHashes($post['hash'], $_post['hash'], 0.15)){
                        $dupl[$_post['id']] = ['src' => $_post['file']];
                        $processed[] = $_post['id'];
                    }
                }
                if(count($dupl) > 1) $duplicates[] = $dupl;
            }
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