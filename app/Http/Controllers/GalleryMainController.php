<?php

namespace App\Http\Controllers;

use App\GalleryCategoryAggregator;
use App\GalleryTagAggregator;
use Illuminate\Http\Request;

class GalleryMainController extends Controller
{


    public function execute(){
        $categories = GalleryCategoryAggregator::getEnabledCategories();

        return view('gallery_main', ['categories' => $categories]);
    }
}
