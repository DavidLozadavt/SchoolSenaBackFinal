<?php

namespace App\Http\Controllers\gestion_chat;

use App\Http\Controllers\Controller;
use App\Models\GrupoChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrupoChatController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\JsonResponse
   * @author Andres Pizo <pizoluligoa@gmail.com>
   */
  public static function index(?Request $request): JsonResponse
  {
    $grupos = GrupoChat::all();
    return response()->json($grupos, 200);
  }

  /**
   * Create new group
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function store(Request $request): JsonResponse
  {
    $data = $request->all();

    $grupo = GrupoChat::create([
      'nombreGrupo'                 => $data['nombreGrupo'],
      'estado'                      => 'ACTIVO',
      'descripcion'                 => $data['descripcion'],
      'cantidadParticipantes'       => $data['cantidadParticipantes'],
      'idTipoGrupo'                 => $data['idTipoGrupo'],
    ]);
    return response()->json($grupo, 201);
  }

  /**
   * Display the specified resource.
   *
   * @param int $id
   * @return JsonResponse
   */
  public static function show(int $id): JsonResponse
  {
    $grupo = GrupoChat::findOrFail($id);
    $grupo->load(
      'comentarios',
      'gradoMateria',
      'matriculas.persona.usuario',
    );
    return response()->json($grupo, 200);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param Request $request
   * @param int $id
   * @return JsonResponse
   */
  public function update(Request $request, int $id): JsonResponse
  {
    $data = $request->all();
    $grupo = GrupoChat::findOrFail($id);
    $grupo->update($data);
    return response()->json($grupo, 200);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param int $id
   * @return JsonResponse
   */
  public function destroy(int $id): JsonResponse
  {
    $grupo = GrupoChat::findOrFail($id);
    $grupo->delete();
    return response()->json(null, 204);
  }
}
