<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class WebController extends Controller
{
    public function index()
    {
        $path = public_path() . '/index.html';
        if (!File::exists($path)) {
            return response()->json([
                'message' => 'Archivo no encontrado'
            ], 404);
        }
        return File::get($path);
    }
}
