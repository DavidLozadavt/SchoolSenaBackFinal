<?php

namespace App\Http\Controllers\gestion_transporte;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Transporte\ConfiguracionVehiculo;

class ConfiguracionVehiculoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $configuracionVehiculo = ConfiguracionVehiculo::all();
        return response()->json($configuracionVehiculo);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $configuracion = ConfiguracionVehiculo::create($request->all());
        return response()->json($configuracion, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  ConfiguracionVehiculo  $configuracionVehiculo
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $config = ConfiguracionVehiculo::findOrFail($id);
        return response()->json($config);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  ConfiguracionVehiculo  $configuracionVehiculo
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $configuracion = ConfiguracionVehiculo::find($id);
        $configuracion->update($request->all());
        return response()->json($configuracion, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  ConfiguracionVehiculo  $configuracionVehiculo
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $configuracion = ConfiguracionVehiculo::find($id);
        $configuracion->delete();
        return response()->json(null, 204);
    }
}
