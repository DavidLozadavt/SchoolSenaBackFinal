<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Actividades asignadas al aprendiz - vista del estudiante.
 */
class ActividadesAprendizController extends Controller
{
    /**
     * Listar actividades asignadas al aprendiz autenticado.
     * Incluye: actividad, fechas, nota, quien asignó, material de apoyo.
     */
    public function index(): JsonResponse
    {
        try {
            $user = KeyUtil::user();
            $idPersona = $user?->idpersona ?? null;
            if (!$idPersona) {
                return response()->json(['data' => [], 'message' => 'Usuario sin persona asociada']);
            }

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['data' => []]);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : null);
            if (!$colFicha) {
                return response()->json(['data' => []]);
            }

            $idsMatriculaAcademica = DB::table($tableMa . ' as ma')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->where('m.idPersona', $idPersona)
                ->pluck('ma.id');

            if ($idsMatriculaAcademica->isEmpty()) {
                return response()->json(['data' => []]);
            }

            $calificaciones = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->leftJoin('persona as p_asigno', 'ca.idPersona', '=', 'p_asigno.id')
                ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
                ->leftJoin('estado as e', 'a.idEstado', '=', 'e.id')
                ->leftJoin('ficha as f', 'ma.' . $colFicha, '=', 'f.id')
                ->leftJoin('aperturarprograma as ap', 'f.idAsignacion', '=', 'ap.id')
                ->leftJoin('jornadas as j', 'f.idJornada', '=', 'j.id')
                ->leftJoin('contrato as c_lider', 'f.idInstructorLider', '=', 'c_lider.id')
                ->leftJoin('persona as p_lider', 'c_lider.idpersona', '=', 'p_lider.id')
                ->whereIn('ca.idAMartriculaAcademica', $idsMatriculaAcademica)
                ->select([
                    'ca.id as idCalificacionActividad',
                    'ca.idActividad',
                    'ca.fechaInicial',
                    'ca.fechaFinal',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.ComentarioDocente',
                    'ca.ComentarioEstudiante',
                    'ca.archivo',
                    'ca.idGrupo',
                    'p_asigno.id as idPersonaInstructor',
                    'p_asigno.rutaFoto as instructorRutaFoto',
                    DB::raw("CONCAT(COALESCE(p_asigno.nombre1,''), ' ', COALESCE(p_asigno.nombre2,''), ' ', COALESCE(p_asigno.apellido1,''), ' ', COALESCE(p_asigno.apellido2,'')) as asignadoPor"),
                    'a.tituloActividad',
                    'a.descripcionActividad',
                    'a.pathDocumentoActividad',
                    'a.tipoActividad',
                    'a.entregables',
                    'a.estrategia',
                    'a.autor',
                    'a.idMateria',
                    'mat.nombreMateria',
                    'mat.codigo as materiaCodigo',
                    'e.estado as estadoActividad',
                    'f.id as idFicha',
                    'f.codigo as fichaCodigo',
                    'ap.estado as fichaEstado',
                    'ap.fechaInicialClases as fichaFechaInicio',
                    'ap.fechaFinalClases as fichaFechaFin',
                    'j.nombreJornada as fichaJornada',
                    'p_lider.rutaFoto as instructorLiderRutaFoto',
                    DB::raw("CONCAT(COALESCE(p_lider.nombre1,''), ' ', COALESCE(p_lider.apellido1,'')) as instructorLiderNombre"),
                ])
                ->orderBy('ca.fechaFinal', 'desc')
                ->get();

            $result = [];
            foreach ($calificaciones as $c) {
                $materiales = [];
                if (Schema::hasTable('asignacionMaterialApoyoActividad') && Schema::hasTable('materialApoyoActividad')) {
                    $materiales = DB::table('asignacionMaterialApoyoActividad as ama')
                        ->join('materialApoyoActividad as ma', 'ama.idMaterialApoyo', '=', 'ma.id')
                        ->where('ama.idActividad', $c->idActividad)
                        ->select('ma.id', 'ma.titulo', 'ma.descripcion', 'ma.urlDocumento', 'ma.urlAdicional')
                        ->get()
                        ->toArray();
                }

                $result[] = [
                    'idCalificacionActividad' => $c->idCalificacionActividad,
                    'idActividad' => $c->idActividad,
                    'codigo' => (string) $c->idActividad,
                    'instructorRutaFoto' => $c->instructorRutaFoto ?? null,
                    'ficha' => $c->idFicha ? [
                        'idFicha' => $c->idFicha,
                        'codigo' => $c->fichaCodigo,
                        'estado' => $c->fichaEstado,
                        'fechaInicio' => $c->fichaFechaInicio,
                        'fechaFin' => $c->fichaFechaFin,
                        'jornada' => $c->fichaJornada,
                        'instructorLider' => [
                            'rutaFoto' => $c->instructorLiderRutaFoto ?? null,
                            'nombre' => trim($c->instructorLiderNombre ?? '') ?: null,
                        ],
                    ] : null,
                    'tituloActividad' => $c->tituloActividad,
                    'descripcionActividad' => $c->descripcionActividad,
                    'pathDocumentoActividad' => $c->pathDocumentoActividad,
                    'tipoActividad' => $c->tipoActividad,
                    'entregables' => $c->entregables,
                    'estrategia' => $c->estrategia,
                    'autor' => $c->autor,
                    'idMateria' => $c->idMateria,
                    'materia' => $c->nombreMateria ? ['nombreMateria' => $c->nombreMateria, 'codigo' => $c->materiaCodigo] : null,
                    'estado' => $c->estadoActividad ? ['estado' => $c->estadoActividad] : null,
                    'fechaInicial' => $c->fechaInicial,
                    'fechaFinal' => $c->fechaFinal,
                    'calificacionNumerica' => $c->calificacionNumerica,
                    'calificacionEstandart' => $c->calificacionEstandart,
                    'ComentarioDocente' => $c->ComentarioDocente,
                    'ComentarioEstudiante' => $c->ComentarioEstudiante,
                    'archivo' => $c->archivo,
                    'asignadoPor' => trim($c->asignadoPor ?? '') ?: null,
                    'materialesApoyo' => $materiales,
                ];
            }

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Entregar/responder actividad - subir archivo o comentario.
     */
    public function entregar(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'idCalificacionActividad' => 'required|integer',
                'archivo' => 'nullable|file|max:10240',
                'ComentarioEstudiante' => 'nullable|string|max:2000',
            ]);

            $validator->after(function ($v) use ($request) {
                if (! $request->hasFile('archivo')) {
                    return;
                }

                $file = $request->file('archivo');
                if (! $file) {
                    return;
                }

                $allowedExtensions = ['pdf', 'doc', 'docx', 'zip', 'rar', 'sql'];
                $ext = strtolower((string) $file->getClientOriginalExtension());

                if ($ext === '' || ! in_array($ext, $allowedExtensions, true)) {
                    $v->errors()->add('archivo', 'Tipo de archivo no permitido. Solo se permiten: PDF, DOC, DOCX, ZIP, RAR, SQL.');
                    return;
                }

                if ($ext === 'sql') {
                    $mime = strtolower((string) ($file->getMimeType() ?? ''));
                    $allowedSqlMimes = [
                        'text/plain',
                        'text/x-sql',
                        'application/sql',
                        'application/x-sql',
                        'application/octet-stream',
                    ];

                    if ($mime !== '' && ! in_array($mime, $allowedSqlMimes, true)) {
                        $v->errors()->add('archivo', 'El archivo SQL no tiene un tipo válido.');
                    }

                    return;
                }

                $secondary = Validator::make(['archivo' => $file], [
                    'archivo' => 'mimes:pdf,doc,docx,zip,rar',
                ]);

                if ($secondary->fails()) {
                    $v->errors()->add('archivo', 'Tipo de archivo no permitido. Solo se permiten: PDF, DOC, DOCX, ZIP, RAR, SQL.');
                }
            });

            $validated = $validator->validate();

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona ?? null;
            if (!$idPersona) {
                return response()->json(['error' => 'Usuario sin persona asociada'], 401);
            }

            $ca = DB::table('calificacionActividad as ca')
                ->join('matriculaAcademica as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->where('ca.id', $validated['idCalificacionActividad'])
                ->where('m.idPersona', $idPersona)
                ->select('ca.id', 'ca.idActividad')
                ->first();

            if (!$ca) {
                return response()->json(['error' => 'No tienes permiso para entregar esta actividad'], 403);
            }

            $update = [];
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $path = $file->store('actividades/entregas/' . $ca->idActividad, 'public');
                $update['archivo'] = $path;
            }
            if (isset($validated['ComentarioEstudiante'])) {
                $update['ComentarioEstudiante'] = $validated['ComentarioEstudiante'];
            }
            $update['updated_at'] = now();

            if (!empty($update)) {
                DB::table('calificacionActividad')->where('id', $ca->id)->update($update);
            }

            return response()->json(['message' => 'Entrega registrada correctamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
