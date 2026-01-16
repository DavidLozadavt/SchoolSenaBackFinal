<?php

namespace App\Http\Controllers\gestion_chat;

use App\Http\Controllers\Controller;
use App\Models\TipoGrupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoGrupoController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request = null): JsonResponse
  {
    $tipoGrupos = TipoGrupo::all();
    return response()->json($tipoGrupos, 200);
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \App\Http\Requests\StoreTipoGrupoRequest  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request): JsonResponse
  {
    $tipoGrupo = TipoGrupo::create($request->all());
    return response()->json($tipoGrupo, 201);
  }

  /**
   * Display the specified resource.
   *
   * @param  \App\Models\TipoGrupo  $tipoGrupo
   * @return \Illuminate\Http\Response
   */
  public function show(int $id): JsonResponse
  {
    $tipoGrupo = tipoGrupo::findOrFail($id);
    return response()->json($tipoGrupo, 200);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \App\Http\Requests\UpdateTipotipoGrupoRequest  $request
   * @param  \App\Models\TipotipoGrupo  $tipotipoGrupo
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, int $id): JsonResponse
  {
    $data = $request->all();
    $tipoGrupo = TipoGrupo::findOrFail($id);
    $tipoGrupo->update($data);
    return response()->json($tipoGrupo, 200);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  \App\Models\TipoGrupo  $tipoGrupo
   * @return \Illuminate\Http\Response
   */
  public function destroy(int $id): JsonResponse
  {
    $tipoGrupo = TipoGrupo::findOrFail($id);
    $tipoGrupo->delete();
    return response()->json(null, 204);
  }
}
