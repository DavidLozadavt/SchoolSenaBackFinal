<?php

namespace App\Http\Controllers\gestion_seguridad_social;

use App\Http\Controllers\Controller;
use App\Models\EntidadesSeguridadSocial;
use Illuminate\Http\Request;

class EntidadesSeguridadSocialController extends Controller
{

    public function index()
    {
        return response()->json(EntidadesSeguridadSocial::all(), 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:EPS,PENSION,ARL,CESANTIAS,CAJA COMPENSACION',
            'descripcion' => 'nullable|string',
            'nit' => 'nullable|string',
            'codigo' => 'nullable|string'
        ]);

        $entidad = EntidadesSeguridadSocial::create($request->all());

        return response()->json([
            'message' => 'Entidad creada correctamente',
            'data' => $entidad
        ], 201);
    }


    public function show($id)
    {
        $entidad = EntidadesSeguridadSocial::findOrFail($id);
        return response()->json($entidad, 200);
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'tipo' => 'sometimes|required|in:EPS,PENSION,ARL,CESANTIAS,CAJA COMPENSACION',
            'descripcion' => 'nullable|string',
            'nit' => 'nullable|string',
            'codigo' => 'nullable|string'
        ]);

        $entidad = EntidadesSeguridadSocial::findOrFail($id);
        $entidad->update($request->all());

        return response()->json([
            'message' => 'Entidad actualizada correctamente',
            'data' => $entidad
        ], 200);
    }

    public function destroy($id)
    {
        $entidad = EntidadesSeguridadSocial::findOrFail($id);
        $entidad->delete();

        return response()->json(['message' => 'Entidad eliminada correctamente'], 200);
    }



    public function getEPS()
    {
        $eps = EntidadesSeguridadSocial::where('tipo', 'EPS')->get();
        return response()->json($eps);
    }


    public function getPensiones()
    {
        $pensiones = EntidadesSeguridadSocial::where('tipo', 'PENSION')->get();
        return response()->json($pensiones);
    }

    public function getARL()
    {
        $arl = EntidadesSeguridadSocial::where('tipo', 'ARL')->get();
        return response()->json($arl);
    }

    public function getCajaCompensacion()
    {
        $arl = EntidadesSeguridadSocial::where('tipo', 'CAJA COMPENSACION')->get();
        return response()->json($arl);
    }

    public function getCesantias()
    {
        $arl = EntidadesSeguridadSocial::where('tipo', 'CESANTIAS')->get();
        return response()->json($arl);
    }
}
