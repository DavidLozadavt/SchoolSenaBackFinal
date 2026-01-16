<?php

namespace App\Http\Controllers\gestion_chat;

use App\Http\Controllers\Controller;
use App\Models\AsignacionComentarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsignacionComentariosController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return JsonResponse
   */
  public function index(Request $request = null): JsonResponse
  {
    $asignacionComentarios = AsignacionComentarios::all();
    return response()->json($asignacionComentarios, 200);
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  Request  $request
   * @return JsonResponse
   */
  public function store(Request $request): JsonResponse
  {
    $asignacionComentario = AsignacionComentarios::create($request->all());
    return response()->json($asignacionComentario, 201);
  }

  /**
   * Display the specified resource.
   *
   * @param  AsignacionComentarios  $asignacionComentarios
   * @return JsonResponse
   */
  public function show(int $id): JsonResponse
  {
    $asignacionComentario = AsignacionComentarios::findOrFail($id);
    return response()->json($asignacionComentario, 200);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param Request  $request
   * @param AsignacionComentarios  $asignacionComentarios
   * @return JsonResponse
   */
  public function update(Request $request, int $id): JsonResponse
  {
    $data = $request->all();
    $asignacionComentario = AsignacionComentarios::findOrFail($id);
    $asignacionComentario->update($data);
    return response()->json($asignacionComentario, 200);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param AsignacionComentarios  $asignacionComentarios
   * @return JsonResponse
   */
  public function destroy(int $id): JsonResponse
  {
    $asignacionComentario = AsignacionComentarios::findOrFail($id);
    $asignacionComentario->delete();
    return response()->json(null, 204);
  }
}
