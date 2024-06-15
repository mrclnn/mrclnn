<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use Throwable;

class GalleryMainController extends Controller
{


    public function execute(){
        $categories = Categories::getEnabled();
        try{
            return view('gallery_main', ['categories' => $categories]);
        } catch(Throwable $e){
            var_dump($e);
        }

    }
}
