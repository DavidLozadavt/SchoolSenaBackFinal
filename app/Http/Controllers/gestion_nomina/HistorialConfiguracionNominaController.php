<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\HistorialConfiguracionNomina;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistorialConfiguracionNominaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $historialConfiguracionesNomina = HistorialConfiguracionNomina::where('idEmpresa', KeyUtil::idCompany())->get();
        return response()->json($historialConfiguracionesNomina);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $request->request->add(['idEmpresa' => KeyUtil::idCompany()]);
        $historialConfiguracionNomina = HistorialConfiguracionNomina::create($request->all());
        return response()->json($historialConfiguracionNomina, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\HistorialConfiguracionNomina  $historialConfiguracionNomina
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $historialConfiguracionNomina = HistorialConfiguracionNomina::findOrFail($id);
        return response()->json($historialConfiguracionNomina);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\HistorialConfiguracionNomina  $historialConfiguracionNomina
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $historialConfiguracionNomina = HistorialConfiguracionNomina::findOrFail($id);
        $historialConfiguracionNomina->update($request->all());
        return response()->json($historialConfiguracionNomina);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\HistorialConfiguracionNomina  $historialConfiguracionNomina
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $historialConfiguracionNomina = HistorialConfiguracionNomina::findOrFail($id);
        $historialConfiguracionNomina->delete();
        return response()->json(null, 204);
    }
}
