<?php

namespace App\Http\Controllers;

use App\GalleryCategoryAggregator;
use App\GalleryTagAggregator;
use App\Models\Categories;
use Illuminate\Http\Request;
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
