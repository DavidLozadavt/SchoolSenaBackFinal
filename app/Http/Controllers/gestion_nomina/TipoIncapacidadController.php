<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\TipoIncapacidad;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TipoIncapacidadController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $tipoIncapacidades = TipoIncapacidad::orderBy('id', 'desc')->get();
        return response()->json($tipoIncapacidades);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $tipoCapacidad = TipoIncapacidad::create($request->all());
        return response()->json($tipoCapacidad, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Nomina\TipoIncapacidad  $tipoIncapacidad
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $tipoCapacidad = TipoIncapacidad::findOrFail($id);
        return response()->json($tipoCapacidad);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Nomina\TipoIncapacidad  $tipoIncapacidad
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tipoIncapacidad = TipoIncapacidad::findOrFail($id);
        $tipoIncapacidad->update($request->all());
        return response()->json($tipoIncapacidad, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Nomina\TipoIncapacidad  $tipoIncapacidad
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $tipoIncapacidad = TipoIncapacidad::findOrFail($id);
        $tipoIncapacidad->delete();
        return response()->json(null, 204);
    }
}
