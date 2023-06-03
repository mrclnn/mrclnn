<?php

namespace App\Http\Controllers;

use App\GalleryCategoryAggregator;
use App\GalleryPostAggregator;
use App\Jobs\TestJob;
use App\Models\Categories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class GalleryViewerController extends Controller
{
    public function execute(){
        $enabledCategories = Categories::getEnabled();
        return view('gallery', ['categories' => $enabledCategories]);
    }
}
