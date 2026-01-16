<?php

declare(strict_types=1);

namespace App\Http\Controllers\gestion_chat;

use App\Http\Controllers\Controller;
use App\Models\AsignacionParticipante;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsignacionParticipanteController extends Controller
{

  public static function index(?Request $request): JsonResponse
  {
    $asignacionParticipantes = AsignacionParticipante::all();
    return response()->json($asignacionParticipantes);
  }

  /**
   * Get asignacionParticipante by id
   *
   * @param integer $id
   * @return JsonResponse
   */
  public function show(int $id): JsonResponse
  {
    $asignacionParticipante = AsignacionParticipante::findOrFail($id);
    return response()->json($asignacionParticipante, 200);
  }

  /**
   * Create new AsignacionParticipante
   *
   * @param Request $request
   * @return void
   */
  public static function store(Request $request): JsonResponse
  {
    $data = $request->all();

    $isUserInSameGroup = self::validateUserInSameGroup($request);

    if ($isUserInSameGroup) {
      return response()->json(['error' => 'No puedes asignarte nuevamente al mismo grupo'], 422);
    }

    $validateCantGroups = self::validateCantUsersInGroup($request);

    if ($validateCantGroups) {
      return response()->json(['error' => 'No puedes asignarte a este grupo porque se encuentra lleno'], 422);
    }

    $asignacionParticipante = AsignacionParticipante::create($data);
    $asignacionParticipante->load('grupo', 'matricula.persona');
    return response()->json($asignacionParticipante, 201);
  }

  /**
   * Validate user if not exists in the same group
   *
   * @param Request $request
   * @return boolean
   */
  private static function validateUserInSameGroup(Request $request): bool
  {
    // Realizar la consulta para verificar si el usuario ya está en el mismo grupo
    $validate = AsignacionParticipante::where('idGrupo', $request['idGrupo'])
      ->where('idMatricula', $request['idMatricula'])
      ->exists();

    // Verificar si la validación es verdadera
    if ($validate) {
      return true;
    }

    return false;
  }

  /**
   * Validate cant student in groups
   *
   * @param Request $request
   * @return boolean
   */
  private static function validateCantUsersInGroup(Request $request): bool
  {
    $group = Grupo::findOrFail($request['idGrupo']);
    $cantStudents = $group->cantidadParticipantes;

    $asignacionParticipantesCount = AsignacionParticipante::where('idGrupo', $request['idGrupo'])->count();

    return $asignacionParticipantesCount >= $cantStudents;
  }

  /**
   * Update asignacionParticipante
   *
   * @param Request $request
   * @param integer $id
   * @return void
   */
  public function update(Request $request, int $id): JsonResponse
  {
    $data = $request->all();
    $asignacionParticipante = AsignacionParticipante::findOrFail($id);
    $asignacionParticipante->update($data);
    return response()->json($asignacionParticipante, 200);
  }

  /**
   * Delete asignacionParticipante by id
   *
   * @param integer $id
   * @return void
   */
  public function destroy(int $id): JsonResponse
  {
    $asignacionParticipante = AsignacionParticipante::findOrFail($id);
    $asignacionParticipante->delete();
    return response()->json(null, 204);
  }
}
