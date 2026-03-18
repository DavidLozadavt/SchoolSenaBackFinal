<?php

namespace App\Http\Controllers\gestion_pensum;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\AsistenciaAdministracion;
use App\Models\Person;
use App\Models\User;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InasistenciaController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public static function index(Request $request = null): JsonResponse
  {

    if ($request === null) {
      $request = new Request();
    }

    // Verificar si se pasaron los parámetros de búsqueda
    if ($request->has(['idSesionMateria', 'idMatriculaAcademica'])) {
      // Obtener inasistencias específicas
      $absences = Asistencia::where('idSesionMateria', $request->idSesionMateria)
        ->where('idMatriculaAcademica', $request->idMatriculaAcademica)
        ->where('asistio', false)
        ->get();

      if ($absences->isEmpty()) {
        return response()->json([], 200);
      }

      $absences->load('sesionMateria.horarioMateria.materia', 'matriculaAcademica.materia.grados.materia');
      return response()->json($absences, 200);
    }

    // Obtener el usuario autenticado con sus relaciones
    $user = User::with(['activationCompanyUsers.roles'])->find(auth()->id());

    // Verificar si el usuario tiene el rol adecuado
    if ($user instanceof User && self::validateRoleUser($user)) {
      // Obtener la persona asociada al usuario
      $person = Person::find($user->idPersona);

      if (!$person) {
        return response()->json(['message' => 'Persona no encontrada'], 404);
      }

      // Obtener la última matrícula de la persona
      $lastMatricula = $person->matriculas()->latest()->first();

      if (!$lastMatricula) {
        return response()->json(['message' => 'No se encontró la última matrícula para la persona'], 404);
      }

      // Obtener inasistencias de todas las matrículas académicas de la última matrícula
      $matriculaAcademicas = $lastMatricula->matriculasAcademicas;

      if ($matriculaAcademicas->isEmpty()) {
        return response()->json([], 404); // No se encontrarón matrículas académicas para la última matrícula
      }

      $allInasistencias = collect();

      foreach ($matriculaAcademicas as $matriculaAcademica) {
        $inasistencias = Asistencia::where('asistio', false)
          ->where('idMatriculaAcademica', $matriculaAcademica->id)
          ->get();

        foreach ($inasistencias as $inasistencia) {
          $inasistencia->load('sesionMateria.horarioMateria.materia', 'matriculaAcademica.materia.grados.materia', 'matriculaAcademica.matricula.persona');
        }

        $allInasistencias = $allInasistencias->merge($inasistencias);
      }

      if ($allInasistencias->isEmpty()) {
        return response()->json([], 200);
      }

      $groupedInasistencias = $allInasistencias->groupBy(function ($inasistencia) {
        return $inasistencia->fechaLLegada; // Agrupar por fecha de creación
      })->sortKeysDesc();

      return response()->json($groupedInasistencias, 200);
    } else {

      return self::getAbsencesByAdmins($user);
    }

  }

  /**
   * Get all absences by teacher, admin, etc.
   *
   * @return JsonResponse
   */
  public static function getAbsencesByAdmins($user): JsonResponse
  {
    if (self::validateRoleUserIsDiferentToStudentOrAttendant($user)) {

      $person = Person::find($user->idPersona);

      $contrato = $person->contrato;

      $allInasistencias = collect();

      $inasistencias = AsistenciaAdministracion::where('asistio', false)
        ->where('idContrato', $contrato->id)
        ->get();

      foreach ($inasistencias as $inasistencia) {
        $inasistencia->load('excusas', 'contrato.persona');
      }

      $allInasistencias = $allInasistencias->merge($inasistencias);

      if ($allInasistencias->isEmpty()) {
        return response()->json([], 200);
      }

      $groupedInasistencias = $allInasistencias->groupBy(function ($inasistencia) {
        return $inasistencia->fechaLLegada; // Agrupar por fecha de creación
      })->sortKeysDesc();

      return response()->json($groupedInasistencias);
    } else {
      return response()->json([
        'message' => 'No tienes permisos para acceder'
      ], 403);
    }
  }

  /**
   * Validate if user is role Student
   *
   * @param User $user
   * @return boolean
   */
  private static function validateRoleUser($user): bool
  {
    // Accediendo al nombre del rol
    $firstRoleName = $user->activationCompanyUsers->first()->roles->first()->name;

    return $firstRoleName == "ESTUDIANTE" ?? false;
  }

  /**
   * Validate if user is diferent to student or attendant
   *
   * @param User $user
   * @return boolean
   */
  private static function validateRoleUserIsDiferentToStudentOrAttendant($user): bool
  {
    // Accediendo al nombre del rol
    $firstRoleName = $user->activationCompanyUsers->first()->roles->first()->name;

    return $firstRoleName != "ESTUDIANTE" || $firstRoleName != "ACUDIENTE" ?? false;
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id): JsonResponse
  {
    $inasistencia = Asistencia::where('id', $id)->where('asistio', false)->first();

    if (!$inasistencia) {
      return response()->json(['message' => 'No se encontró ninguna información por el id y estado inasistida'], 404);
    }

    return response()->json($inasistencia);
  }
}
