<?php

namespace App\Http\Controllers;

use App\Models\HorarioMateria;
use App\Models\MatriculaAcademica;
use App\Models\Matricula;
use App\Models\Ficha;
use App\Models\Contract;
use App\Models\Sede;
use App\Models\Company;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LyraController extends Controller
{
    /**
     * Obtiene las clases del día actual para el usuario autenticado.
     */
    public function clasesHoy(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;
            $hoy = Carbon::now();
            $idDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

            // Intentar obtener como instructor
            $contrato = KeyUtil::lastContractActive();
            if ($contrato) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->where('idContrato', $contrato->id)
                    ->where('idDia', $idDia)
                    ->get();

                return response()->json([
                    'role' => 'instructor',
                    'data' => $clases
                ]);
            }

            // Intentar obtener como estudiante
            $matriculas = MatriculaAcademica::whereHas('matricula', function($q) use ($idPersona) {
                $q->where('idPersona', $idPersona);
            })->pluck('idFicha');

            if ($matriculas->isNotEmpty()) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->whereIn('idFicha', $matriculas)
                    ->where('idDia', $idDia)
                    ->get();

                return response()->json([
                    'role' => 'estudiante',
                    'data' => $clases
                ]);
            }

            return response()->json(['data' => [], 'message' => 'No se encontraron clases para hoy']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las notas (calificaciones) del estudiante autenticado.
     */
    public function misNotas(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;

            $calificaciones = DB::table('calificacionActividad as ca')
                ->join('matriculaAcademica as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->where('m.idPersona', $idPersona)
                ->select([
                    'a.tituloActividad',
                    'mat.nombreMateria',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.fechaCalificacion'
                ])
                ->get();

            return response()->json($calificaciones);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las actividades pendientes para el estudiante autenticado.
     */
    public function pendientes(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;

            $pendientes = DB::table('calificacionActividad as ca')
                ->join('matriculaAcademica as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->where('m.idPersona', $idPersona)
                ->where(function($q) {
                    $q->whereNull('ca.calificacionNumerica')
                      ->orWhere('ca.calificacionNumerica', '');
                })
                ->where(function($q) {
                    $q->whereNull('ca.archivo')
                      ->orWhere('ca.archivo', '');
                })
                ->select([
                    'a.tituloActividad',
                    'a.descripcionActividad',
                    'ca.fechaFinal as fechaLimite',
                    'mat.nombreMateria'
                ])
                ->get();

            return response()->json($pendientes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene todas las fichas activas (para administradores/coordinadores).
     */
    public function fichasActivas(Request $request): JsonResponse
    {
        try {
            if (!KeyUtil::hasRole(['INSTRUCTOR', 'ADMIN', 'COORDINADOR', 'REGIONAL', 'CENTRO', 'VT'])) {
                return response()->json(['error' => 'No autorizado para ver fichas activas'], 403);
            }

            $idCompany = KeyUtil::idCompany();
            
            $fichas = Ficha::with(['aperturarPrograma.programa', 'jornada'])
                ->where('idRegional', $idCompany)
                ->get()
                ->map(fn($f) => [
                    'id' => $f->id,
                    'codigo' => $f->codigo,
                    'programa' => $f->aperturarPrograma?->programa?->nombrePrograma ?? 'N/A',
                    'jornada' => $f->jornada?->nombre ?? 'N/A',
                ]);

            return response()->json($fichas);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las entregas (respuestas) de una actividad.
     */
    public function getEntregas(int $idActividad): JsonResponse
    {
        try {
            $entregas = DB::table('calificacionActividad as ca')
                ->join('matriculaAcademica as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('persona as p', 'm.idPersona', '=', 'p.id')
                ->where('ca.idActividad', $idActividad)
                ->select([
                    'p.nombre1',
                    'p.apellido1',
                    'ca.archivo',
                    'ca.fechaCalificacion',
                    'ca.calificacionNumerica'
                ])
                ->get();

            return response()->json($entregas);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene la cantidad de estudiantes matriculados en una ficha.
     */
    public function getEstudiantesFicha(int $idFicha): JsonResponse
    {
        try {
            $total = MatriculaAcademica::where('idFicha', $idFicha)->count();
            $ficha = Ficha::find($idFicha);

            return response()->json([
                'ficha_id' => $idFicha,
                'codigo' => $ficha ? $ficha->codigo : null,
                'total_estudiantes' => $total
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el horario semanal completo del usuario autenticado.
     */
    public function horarioSemana(Request $request): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            if (!$user) {
                return response()->json(['error' => 'No autenticado'], 401);
            }

            $idPersona = $user->idpersona;

            // Intentar obtener como instructor
            $contrato = KeyUtil::lastContractActive();
            if ($contrato) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->where('idContrato', $contrato->id)
                    ->orderBy('idDia')
                    ->orderBy('horaInicial')
                    ->get();

                return response()->json([
                    'role' => 'instructor',
                    'data' => $clases
                ]);
            }

            // Intentar obtener como estudiante
            $matriculas = MatriculaAcademica::whereHas('matricula', function($q) use ($idPersona) {
                $q->where('idPersona', $idPersona);
            })->pluck('idFicha');

            if ($matriculas->isNotEmpty()) {
                $clases = HorarioMateria::with(['gradoMateria.materia', 'ficha', 'dia'])
                    ->whereIn('idFicha', $matriculas)
                    ->orderBy('idDia')
                    ->orderBy('horaInicial')
                    ->get();

                return response()->json([
                    'role' => 'estudiante',
                    'data' => $clases
                ]);
            }

            return response()->json(['data' => [], 'message' => 'No se encontró horario semanal']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene un resumen general para administradores.
     */
    public function resumenAdmin(Request $request): JsonResponse
    {
        try {
            if (!KeyUtil::hasRole(['ADMIN', 'COORDINADOR', 'REGIONAL', 'CENTRO', 'VT'])) {
                return response()->json(['error' => 'No autorizado para ver resumen administrativo'], 403);
            }

            $idCompany = KeyUtil::idCompany();
            
            $instructoresActivos = Contract::where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->count();

            $fichasEnEjecucion = Ficha::where('idRegional', $idCompany)->count();

            // Count enrolled students via fichas belonging to this regional
            $fichaIds = Ficha::where('idRegional', $idCompany)->pluck('id');
            $aprendicesMatriculados = $fichaIds->isNotEmpty()
                ? MatriculaAcademica::whereIn('idFicha', $fichaIds)->distinct('idMatricula')->count('idMatricula')
                : 0;

            $sedesActivas = Sede::where('idEmpresa', $idCompany)->count();

            $regional = Company::find($idCompany);

            return response()->json([
                'regional' => $regional->nombre ?? 'N/A',
                'instructores_activos' => $instructoresActivos,
                'fichas_en_ejecucion' => $fichasEnEjecucion,
                'aprendices_matriculados' => $aprendicesMatriculados,
                'sedes_activas' => $sedesActivas,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene un resumen de la nómina (total de salarios activos).
     */
    public function nominaResumen(Request $request): JsonResponse
    {
        try {
            if (!KeyUtil::hasRole(['ADMIN', 'COORDINADOR', 'REGIONAL', 'CENTRO', 'VT'])) {
                return response()->json(['error' => 'No autorizado para ver nómina'], 403);
            }

            $idCompany = KeyUtil::idCompany();
            
            $contratos = Contract::with('salario')
                ->where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->get();

            $totalSalarios = $contratos->sum(fn($c) => $c->salario->valor ?? 0);

            return response()->json([
                'total_nomina_mensual' => $totalSalarios,
                'total_contratos' => $contratos->count(),
                'moneda' => 'COP',
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista todos los instructores activos con su información básica.
     */
    public function listaInstructores(Request $request): JsonResponse
    {
        try {
            if (!KeyUtil::hasRole(['ADMIN', 'COORDINADOR', 'REGIONAL', 'CENTRO', 'VT'])) {
                return response()->json(['error' => 'No autorizado para ver lista de instructores'], 403);
            }
            $idCompany = KeyUtil::idCompany();
            
            $instructores = Contract::with(['persona', 'tipoContrato'])
                ->where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->get()
                ->map(function($c) {
                    return [
                        'id' => $c->id,
                        'nombre' => $c->persona ? ($c->persona->nombre1 . ' ' . $c->persona->apellido1) : 'Sin nombre',
                        'tipo' => $c->tipoContrato ? $c->tipoContrato->nombre : 'N/A',
                        'fecha_fin' => $c->fechaFinalContrato
                    ];
                });

            return response()->json($instructores);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene contratos próximos a vencer (30 días).
     */
    public function contratosVencimiento(Request $request): JsonResponse
    {
        try {
            if (!KeyUtil::hasRole(['ADMIN', 'COORDINADOR', 'REGIONAL', 'CENTRO', 'VT'])) {
                return response()->json(['error' => 'No autorizado para ver vencimientos de contrato'], 403);
            }
            $idCompany = KeyUtil::idCompany();
            $fechaLimite = now()->addDays(30);
            
            $contratos = Contract::with(['persona'])
                ->where('idempresa', $idCompany)
                ->where('idEstado', 1)
                ->whereBetween('fechaFinalContrato', [now(), $fechaLimite])
                ->get();

            return response()->json($contratos);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
