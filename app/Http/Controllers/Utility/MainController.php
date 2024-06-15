<?php

namespace App\Http\Controllers\Utility;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MainController extends Controller
{
    public function jsonToCLass(Request $request): View
    {
        return view('utility_jsonToClass');
    }
}
