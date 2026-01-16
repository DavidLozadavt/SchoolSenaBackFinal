<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    public function index(){
        $departamentos = Country::all();
        return response()->json($departamentos);
    }
}
