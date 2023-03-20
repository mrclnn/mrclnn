<?php

namespace App\Http\Controllers;

use App\GalleryCategoryAggregator;
use App\GalleryPostAggregator;
use App\Jobs\TestJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class GalleryViewerController extends Controller
{
    public function execute(){
//        return 'sorry';
        // должен сформировать объект-окружение который передаст в view
        // какого вида должен быть объект-окружение? стоит ли написать интерфейс для объекта окружения

        // по сути здесь необходимо получить только данные для главного меню, т.е. список категорий/тегов

        // массив

        // одна предопределенная колонка "все посты"
        // остальные - объединенные в подгруппы по типу тега и взятые из enabled tags
        // получается что нужен тип

        // по сути тут должен быть массив с объектами моделями категорий
        $enabledCategories = GalleryCategoryAggregator::getEnabledCategories();

//        dd($enabledCategories);

//        $query = <<<SQL
//select
//    type,
//    tag,
//    count
//from tags
//where enabled = true
//SQL;
//        $categories = DB::select($query);

//        dd($categories);


//        dd($categoriesFinal);

//        foreach($categories as $category){
////            var_dump($category);
//            if(empty($categoriesFinal[$category->type])){
//                $categoriesFinal[$category->type] = [
//                    'name' => $category->type,
//                    'categories' => []
//                ];
//            }
//            $categoriesFinal[$category->type]['categories'][] = [
//                'count' => $category->count,
//                'tag' => $category->tag,
//            ];
//        }

        return view('gallery', ['categories' => $enabledCategories]);
    }
}
