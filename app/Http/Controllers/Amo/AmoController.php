<?php

namespace App\Http\Controllers\Amo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AmoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function amo(Request $request)
    {
        return view('amo');
    }
}