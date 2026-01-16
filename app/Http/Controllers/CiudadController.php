<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class CiudadController extends Controller
{
    //

    public function byDepartamento($idDepartamento)
    {
        $ciudades = City::with('departamento')->where('iddepartamento', '=',$idDepartamento)->get();
        return response()->json($ciudades);
    }

    public function ciudades()
    {
        $ciudades = City::all();
        return response()->json($ciudades);
    }
}
