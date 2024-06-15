<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Categories;

class GalleryViewerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function execute(){
        $enabledCategories = Categories::getEnabled();
        return view('gallery', ['categories' => $enabledCategories]);
    }
}
