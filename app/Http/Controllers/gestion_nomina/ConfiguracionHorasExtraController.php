<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\ConfiguracionHoraExtra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracionHorasExtraController extends Controller
{
  /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $configuracionesHE = ConfiguracionHoraExtra::orderBy('id', 'desc')->get();
        return response()->json($configuracionesHE, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $configuracionHE = ConfiguracionHoraExtra::create($request->all());
        return response()->json($configuracionHE, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  string $id
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $configuracionHE = ConfiguracionHoraExtra::findOrFail($id);
        return response()->json($configuracionHE, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $configuracionHE = ConfiguracionHoraExtra::findOrFail($id);
        $configuracionHE->update($request->all());
        return response()->json($configuracionHE, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $configuracionHE = ConfiguracionHoraExtra::findOrFail($id);
        $configuracionHE->delete();
        return response()->json(null, 204);
    }
}
