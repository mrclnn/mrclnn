<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class GalleryPostController extends Controller
{
    use Logger;
    public function __construct()
    {
        $this->middleware('auth');
        $this->setLogger();
    }

    public function get(int $id): Response
    {
        $path = DB::table('posts')->where('post_id', $id)->first()->file_name ?? null;
        $path = ($path && Storage::disk('gallery_posts')->exists($path)) ? Storage::disk('gallery_posts')->path($path) : public_path('img/no-file.png');
        return response()->file($path);
    }
}