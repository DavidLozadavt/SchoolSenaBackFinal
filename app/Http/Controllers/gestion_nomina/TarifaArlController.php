<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Nomina\TarifaArl;
use Illuminate\Http\JsonResponse;

class TarifaArlController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $tarifas = TarifaArl::orderBy('id', 'desc')->get();
        return response()->json($tarifas, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $tarifa = TarifaArl::create($request->all());
        return response()->json($tarifa, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\TarifaArl  $tarifaArl
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $tarifa = TarifaArl::findOrFail($id);
        return response()->json($tarifa, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TarifaArl  $tarifaArl
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tarifa = TarifaArl::findOrFail($id);
        $tarifa->update($request->all());
        return response()->json($tarifa, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\TarifaArl  $tarifaArl
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $tarifa = TarifaArl::findOrFail($id);
        $tarifa->delete();
        return response()->json(null, 204);
    }
}
