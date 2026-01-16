<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class WebController extends Controller
{
    public function index()
    {
        return File::get(public_path() . '/index.html');
    }
}
