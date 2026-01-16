<?php

namespace App\Http\Controllers\gestion_configuracion_servicio;

use App\Http\Controllers\Controller;
use App\Models\ResponsableServicio;
use Illuminate\Http\Request;

class ResponsableServicioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $responsble = ResponsableServicio::all();

        return response()->json($responsble);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $responsble = new ResponsableServicio($data);
        $responsble->save();

        return response()->json($responsble, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->all();
        $responsble = ResponsableServicio::findOrFail($id);
        $responsble->fill($data);
        $responsble->save();

        return response()->json($responsble);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $responsble = ResponsableServicio::findOrFail($id);
        $responsble->delete();

        return response()->json([], 204);
    }
}
