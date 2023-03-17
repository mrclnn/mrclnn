<?php

namespace App\Http\Controllers;

use App\Jobs\TestJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class GalleryViewerController extends Controller
{
    public function execute(){
//        return 'sorry';

        try{
//            $categories = DB::select('SELECT
//    ht.tag,
//    ht.tag as name,
//    ht.tag as dir_name,
//    ht.count as max
//FROM tags ht
//WHERE ht.type in (\'copyright\',\'character\') AND ht.count > 200
//ORDER BY ht.count DESC');
//            $categories = DB::select('SELECT
//    COUNT(*) AS max,
//       \'original\' as name,
//       \'original\' as tag,
//       \'original\' as dir_name
//FROM posts hp
//WHERE hp.category_id = 40 AND hp.debug = 0 AND hp.tags_artist != \'\'
//');

            $copyright_categories = DB::select('SELECT
    count(hp.id) AS max,
    tag as name,
    CONCAT(\'copyright_\', tag) as tag,
    tag as dir_name
FROM tags ht
join posts hp on hp.tags_copyright like concat(\'%\', ht.tag, \'%\')
WHERE ht.enabled = 1 and type = \'copyright\' and tag != \'\'
group by tag
order by max desc
');
            $char_categories = DB::select('SELECT
    count(hp.id) AS max,
    tag as name,
    CONCAT(\'character_\', tag) as tag,
    tag as dir_name
FROM tags ht
join posts hp on hp.tags_character like concat(\'%\', ht.tag, \'%\')
WHERE ht.enabled = 1 and type = \'character\' and tag != \'\'
group by tag
order by max desc
');

//            var_dump($copyright_categories);
//            die;


//            $categories = DB::select('SELECT
//    hc.tag,
//    hc.name as name,
//    hc.dir_name as dir_name,
//    200 as max
//FROM categories hc
//WHERE hc.id = 40
//');
        } catch (Throwable $e){
            echo $e->getMessage();
        }


        $favoritesCount = DB::select('SELECT count(*) as count FROM posts WHERE status = 2 AND estimate_at > \'2021-06-09\'')[0]->count;

//        $gifCount = DB::select('SELECT count(*) as count FROM posts WHERE file_name LIKE \'%.gif%\'')[0]->count;

        $authorCount = DB::select('SELECT count(*) as count FROM posts hp JOIN categories hc on hp.category_id = hc.id WHERE hc.type = 1')[0]->count;

        $allCount = 0;
//        foreach ($categories as $category) {
//            $allCount += $category->max;
//        }
        $categories = array_merge($char_categories, $copyright_categories);

//        array_unshift($categories, (object)[
//            'id' => 0,
//            'name' => 'gif',
//            'tag' => 'gif',
//            'max' => $gifCount,
//            'dir_name' => 'gif',
//            'downloading' => false,
//            'updating' => false
//        ]);
        array_unshift($categories, (object)[
            'id' => 0,
            'name' => 'authors',
            'tag' => 'authors',
            'max' => $authorCount,
            'dir_name' => 'authors',
            'downloading' => false,
            'updating' => false
        ]);
        array_unshift($categories, (object)[
            'id' => 0,
            'name' => 'fav',
            'tag' => 'fav',
            'max' => $authorCount,
            'dir_name' => 'fav',
            'downloading' => false,
            'updating' => false
        ]);
        array_unshift($categories, (object)[
            'id' => 0,
            'name' => 'favorites',
            'tag' => 'favorites',
            'max' => $favoritesCount,
            'dir_name' => 'favorites',
            'downloading' => false,
            'updating' => false
        ]);
//        array_unshift($categories, (object)[
//            'id' => 0,
//            'name' => 'all',
//            'tag' => 'all',
//            'max' => 10,
//            'dir_name' => 'all',
//            'downloading' => false,
//            'updating' => false
//        ]);
        return view('gallery', ['categories' => $categories]);
    }
}
