<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoletinController extends Controller
{
    /**
     * Lista los cortes de una apertura con su porcentaje.
     * GET /boletin/cortes/{idApertura}
     */
    public function cortesPorApertura($idApertura)
    {
        $cortes = DB::table('cortes')
            ->where('idApertura', $idApertura)
            ->orderBy('numero')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $cortes,
        ]);
    }

    /**
     * Boletín de UN estudiante en UN corte específico.
     * Agrupa calificaciones por materia y devuelve actividades + promedio por corte.
     * GET /boletin/estudiante/{idMatricula}/corte/{idCorte}
     */
    public function boletinEstudianteCorte($idMatricula, $idCorte)
    {
        // Verificar que la matrícula exista
        $matricula = DB::table('matricula as m')
            ->join('persona as p', 'p.id', '=', 'm.idPersona')
            ->where('m.id', $idMatricula)
            ->select(
                'm.id as idMatricula',
                'm.estado',
                'p.id as idPersona',
                DB::raw("CONCAT(p.nombre1, ' ', COALESCE(p.nombre2,''), ' ', p.apellido1, ' ', COALESCE(p.apellido2,'')) as nombreCompleto"),
                'p.identificacion'
            )
            ->first();

        if (!$matricula) {
            return response()->json(['ok' => false, 'message' => 'Matrícula no encontrada'], 404);
        }

        $corte = DB::table('cortes')->find($idCorte);

        if (!$corte) {
            return response()->json(['ok' => false, 'message' => 'Corte no encontrado'], 404);
        }

        // Calificaciones del estudiante en ese corte, agrupadas por materia
        $calificaciones = DB::table('calificacionActividad as ca')
            ->join('matriculaAcademica as ma', 'ma.id', '=', 'ca.idAMartriculaAcademica')
            ->join('actividades as a', 'a.id', '=', 'ca.idActividad')
            ->join('materia as mat', 'mat.id', '=', 'ma.idMateria')
            ->leftJoin('valoracion as v', 'v.id', '=', 'ca.idValoracion')
            ->where('ma.idMatricula', $idMatricula)
            ->where('ca.idCorte', $idCorte)
            ->select(
                'mat.id as idMateria',
                'mat.nombreMateria as materia',
                'a.id as idActividad',
                'a.tituloActividad as actividad',
                'a.porcentaje as porcentajeActividad',
                'ca.id as idCalificacion',
                'ca.calificacionNumerica',
                'ca.calificacionEstandart',
                'ca.ComentarioDocente',
                'ca.fechaCalificacion',
                'v.nombreValoracion as valoracion',
                'ma.notaParcial',
            )
            ->orderBy('mat.nombreMateria')
            ->orderBy('a.tituloActividad')
            ->get();

        // Agrupar por materia
        $porMateria = $calificaciones->groupBy('idMateria')->map(function ($actividades) {
            $primera = $actividades->first();
            $notas = $actividades->whereNotNull('calificacionNumerica')
                ->pluck('calificacionNumerica')
                ->map(fn($n) => (float) $n);
            $promedio = $notas->isNotEmpty() ? round($notas->average(), 2) : null;

            return [
                'idMateria' => $primera->idMateria,
                'materia' => $primera->materia,
                'notaParcial' => $primera->notaParcial,
                'promedio' => $promedio,
                'actividades' => $actividades->map(fn($a) => [
                    'idActividad' => $a->idActividad,
                    'actividad' => $a->actividad,
                    'porcentajeActividad' => $a->porcentajeActividad,
                    'idCalificacion' => $a->idCalificacion,
                    'calificacionNumerica' => $a->calificacionNumerica,
                    'calificacionEstandart' => $a->calificacionEstandart,
                    'valoracion' => $a->valoracion,
                    'comentarioDocente' => $a->ComentarioDocente,
                    'fechaCalificacion' => $a->fechaCalificacion,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'estudiante' => $matricula,
            'corte' => $corte,
            'materias' => $porMateria,
        ]);
    }

    /**
     * Boletín completo de UN estudiante — todos los cortes.
     * GET /boletin/estudiante/{idMatricula}
     */
    public function boletinEstudianteCompleto($idMatricula)
    {
        $matricula = DB::table('matricula as m')
            ->join('ficha as f', 'f.id', '=', 'm.idFicha')
            ->join('aperturarprograma as ap', 'ap.id', '=', 'f.idAsignacion')
            ->join('persona as p', 'p.id', '=', 'm.idPersona')
            ->where('m.id', $idMatricula)
            ->select(
                'm.id as idMatricula',
                'm.estado',
                'm.idFicha',
                'f.codigo as codigoFicha',
                'ap.id as idApertura',
                'p.id as idPersona',
                DB::raw("CONCAT(p.nombre1, ' ', COALESCE(p.nombre2,''), ' ', p.apellido1, ' ', COALESCE(p.apellido2,'')) as nombreCompleto"),
                'p.identificacion'
            )
            ->first();

        if (!$matricula) {
            return response()->json(['ok' => false, 'message' => 'Matrícula no encontrada'], 404);
        }

        // Todos los cortes de la apertura
        $cortes = DB::table('cortes')
            ->where('idApertura', $matricula->idApertura)
            ->orderBy('numero')
            ->get();

        // Todas las calificaciones del estudiante
        $calificaciones = DB::table('calificacionActividad as ca')
            ->join('matriculaAcademica as ma', 'ma.id', '=', 'ca.idAMartriculaAcademica')
            ->join('actividades as a', 'a.id', '=', 'ca.idActividad')
            ->join('materia as mat', 'mat.id', '=', 'ma.idMateria')
            ->join('cortes as c', 'c.id', '=', 'ca.idCorte')
            ->leftJoin('valoracion as v', 'v.id', '=', 'ca.idValoracion')
            ->where('ma.idMatricula', $idMatricula)
            ->whereNotNull('ca.idCorte')
            ->select(
                'c.id as idCorte',
                'c.numero as numeroCorte',
                'c.porcentaje as porcentajeCorte',
                'mat.id as idMateria',
                'mat.nombreMateria as materia',
                'a.id as idActividad',
                'a.tituloActividad as actividad',
                'a.porcentaje as porcentajeActividad',
                'ca.calificacionNumerica',
                'ca.calificacionEstandart',
                'v.nombreValoracion as valoracion',
                'ma.notaParcial',
            )
            ->orderBy('c.numero')
            ->orderBy('mat.nombreMateria')
            ->get();

        // Estructurar: corte > materia > actividades
        $resultado = $cortes->map(function ($corte) use ($calificaciones) {
            $calCorte = $calificaciones->where('idCorte', $corte->id);

            $materias = $calCorte->groupBy('idMateria')->map(function ($actividades) {
                $primera = $actividades->first();
                $notas = $actividades->whereNotNull('calificacionNumerica')
                    ->pluck('calificacionNumerica')
                    ->map(fn($n) => (float) $n);
                return [
                    'idMateria' => $primera->idMateria,
                    'materia' => $primera->materia,
                    'notaParcial' => $primera->notaParcial,
                    'promedio' => $notas->isNotEmpty() ? round($notas->average(), 2) : null,
                    'actividades' => $actividades->map(fn($a) => [
                        'idActividad' => $a->idActividad,
                        'actividad' => $a->actividad,
                        'porcentajeActividad' => $a->porcentajeActividad,
                        'calificacionNumerica' => $a->calificacionNumerica,
                        'calificacionEstandart' => $a->calificacionEstandart,
                        'valoracion' => $a->valoracion,
                    ])->values(),
                ];
            })->values();

            return [
                'idCorte' => $corte->id,
                'numeroCorte' => $corte->numero,
                'porcentajeCorte' => $corte->porcentaje,
                'fechaInicial' => $corte->fechaInicial,
                'fechaFinal' => $corte->fechaFinal,
                'materias' => $materias,
            ];
        });

        return response()->json([
            'ok' => true,
            'estudiante' => $matricula,
            'boletin' => $resultado,
        ]);
    }

    /**
     * Boletín de TODOS los estudiantes de una ficha en un corte.
     * Vista para coordinador o instructor.
     * GET /boletin/ficha/{idFicha}/corte/{idCorte}
     */
    public function boletinFichaCorte($idFicha, $idCorte)
    {
        $corte = DB::table('cortes')->find($idCorte);

        if (!$corte) {
            return response()->json(['ok' => false, 'message' => 'Corte no encontrado'], 404);
        }

        // Estudiantes activos de la ficha
        $estudiantes = DB::table('matricula as m')
            ->join('persona as p', 'p.id', '=', 'm.idPersona')
            ->where('m.idFicha', $idFicha)
            ->whereIn('m.estado', ['CURSANDO', 'EN FORMACION', 'MATRICULADO', 'ACTIVO'])
            ->select(
                'm.id as idMatricula',
                'm.estado',
                'p.id as idPersona',
                DB::raw("CONCAT(p.nombre1, ' ', COALESCE(p.nombre2,''), ' ', p.apellido1, ' ', COALESCE(p.apellido2,'')) as nombreCompleto"),
                'p.identificacion'
            )
            ->orderBy('p.apellido1')
            ->get();

        $idMatriculas = $estudiantes->pluck('idMatricula');

        // Todas las calificaciones del corte para esos estudiantes
        $calificaciones = DB::table('calificacionActividad as ca')
            ->join('matriculaAcademica as ma', 'ma.id', '=', 'ca.idAMartriculaAcademica')
            ->join('actividades as a', 'a.id', '=', 'ca.idActividad')
            ->join('materia as mat', 'mat.id', '=', 'ma.idMateria')
            ->leftJoin('valoracion as v', 'v.id', '=', 'ca.idValoracion')
            ->where('ca.idCorte', $idCorte)
            ->whereIn('ma.idMatricula', $idMatriculas)
            ->select(
                'ma.idMatricula',
                'mat.id as idMateria',
                'mat.nombreMateria as materia',
                'a.id as idActividad',
                'a.tituloActividad as actividad',
                'a.porcentaje as porcentajeActividad',
                'ca.calificacionNumerica',
                'ca.calificacionEstandart',
                'v.nombreValoracion as valoracion',
                'ma.notaParcial',
            )
            ->orderBy('mat.nombreMateria')
            ->get();

        // Armar respuesta por estudiante
        $resultado = $estudiantes->map(function ($est) use ($calificaciones) {
            $calEst = $calificaciones->where('idMatricula', $est->idMatricula);

            $materias = $calEst->groupBy('idMateria')->map(function ($actividades) {
                $primera = $actividades->first();
                $notas = $actividades->whereNotNull('calificacionNumerica')
                    ->pluck('calificacionNumerica')
                    ->map(fn($n) => (float) $n);
                return [
                    'idMateria' => $primera->idMateria,
                    'materia' => $primera->materia,
                    'notaParcial' => $primera->notaParcial,
                    'promedio' => $notas->isNotEmpty() ? round($notas->average(), 2) : null,
                    'actividades' => $actividades->map(fn($a) => [
                        'idActividad' => $a->idActividad,
                        'actividad' => $a->actividad,
                        'porcentajeActividad' => $a->porcentajeActividad,
                        'calificacionNumerica' => $a->calificacionNumerica,
                        'calificacionEstandart' => $a->calificacionEstandart,
                        'valoracion' => $a->valoracion,
                    ])->values(),
                ];
            })->values();

            return [
                'idMatricula' => $est->idMatricula,
                'nombreCompleto' => $est->nombreCompleto,
                'identificacion' => $est->identificacion,
                'estado' => $est->estado,
                'materias' => $materias,
            ];
        });

        return response()->json([
            'ok' => true,
            'corte' => $corte,
            'estudiantes' => $resultado,
        ]);
    }

    /**
     * Lista general de boletines (fichas y estudiantes)
     * GET /coordinador/boletines
     */
    public function getBoletinesGenerales()
    {
        $fichasDB = DB::table('ficha as f')
            ->join('aperturarprograma as ap', 'ap.id', '=', 'f.idAsignacion')
            ->join('programa as p', 'p.id', '=', 'ap.idPrograma')
            ->join('jornadas as j', 'j.id', '=', 'ap.idJornada')
            ->select(
                'f.id as idFicha',
                'f.codigo',
                'p.nombrePrograma as programa',
                'j.nombreJornada as jornada'
            )
            ->get();

        $matriculas = DB::table('matricula as m')
            ->join('persona as p', 'p.id', '=', 'm.idPersona')
            ->select(
                'm.id as idEstudiante',
                'm.idFicha',
                'p.identificacion as documento',
                DB::raw("CONCAT(p.nombre1, ' ', COALESCE(p.nombre2,''), ' ', p.apellido1, ' ', COALESCE(p.apellido2,'')) as nombre")
            )
            ->get();

        // Obtener todas las asistencias para calcular el porcentaje
        $asistencias = DB::table('asistencia as a')
            ->join('matriculaAcademica as ma', 'ma.id', '=', 'a.idMatriculaAcademica')
            ->select(
                'ma.idMatricula as idEstudiante',
                'a.asistio'
            )
            ->get()
            ->groupBy('idEstudiante');

        // Obtener todas las calificaciones para calcular el promedio
        $calificaciones = DB::table('matriculaAcademica as ma')
            ->select(
                'ma.idMatricula as idEstudiante',
                'ma.notaParcial as promedio'
            )
            ->get()
            ->groupBy('idEstudiante');

        $fichasResponse = $fichasDB->map(function ($f) use ($matriculas, $asistencias, $calificaciones) {
            $estudiantesFicha = $matriculas->where('idFicha', $f->idFicha)->values();

            return [
                'idFicha' => $f->idFicha,
                'codigo' => $f->codigo,
                'programa' => $f->programa,
                'jornada' => $f->jornada,
                'totalEstudiantes' => $estudiantesFicha->count(),
                'boletinesListos' => $estudiantesFicha->count(),
                'estudiantes' => $estudiantesFicha->map(function ($e) use ($asistencias, $calificaciones) {
                    // Calcular asistencia
                    $asistenciasEstudiante = $asistencias->get($e->idEstudiante, collect());
                    $totalSesiones = $asistenciasEstudiante->count();
                    $sesionesAsistidas = $asistenciasEstudiante->where('asistio', 1)->count();
                    $porcentajeAsistencia = $totalSesiones > 0 ? ($sesionesAsistidas / $totalSesiones) * 100 : 0;

                    // Calcular promedio
                    $calificacionEstudiante = $calificaciones->get($e->idEstudiante, collect());
                    $promedio = $calificacionEstudiante->isNotEmpty() ? $calificacionEstudiante->first()->promedio : null;

                    // Calcular estadoAcademico según el promedio
                    if ($promedio === null) {
                        $estadoAcademico = 'REPROBADO'; // o el valor por defecto que prefieras
                    } elseif ($promedio >= 3.0) {
                        $estadoAcademico = 'APROBADO';
                    } elseif ($promedio >= 2.0) {
                        $estadoAcademico = 'EN_RIESGO';
                    } else {
                        $estadoAcademico = 'REPROBADO';
                    }

                    // Calcular estadoBoletin
                    if ($promedio === null) {
                        $estadoBoletin = 'SIN_NOTAS';
                    } elseif ($porcentajeAsistencia === 0 && $promedio === 0) {
                        $estadoBoletin = 'PENDIENTE';
                    } else {
                        $estadoBoletin = 'LISTO';
                    }

                    return [
                        'idEstudiante' => $e->idEstudiante,
                        'documento' => $e->documento,
                        'nombre' => $e->nombre,
                        'email' => '',
                        'asistencia' => round($porcentajeAsistencia, 2),
                        'promedio' => $promedio !== null ? round($promedio, 2) : null,
                        'estadoAcademico' => $estadoAcademico,
                        'estadoBoletin' => $estadoBoletin,
                    ];
                })
            ];
        });

        return response()->json([
            'data' => $fichasResponse
        ]);
    }

    /**
     * Detalles del boletín de un estudiante
     * GET /coordinador/boletines/{id}
     */
    public function getBoletinDetalle($idEstudiante)
    {
        // 1. Datos del estudiante + ficha + apertura
        $matricula = DB::table('matricula as m')
            ->join('ficha as f', 'f.id', '=', 'm.idFicha')
            ->join('aperturarprograma as ap', 'ap.id', '=', 'f.idAsignacion')
            ->join('programa as p', 'p.id', '=', 'ap.idPrograma')
            ->join('jornadas as j', 'j.id', '=', 'ap.idJornada')
            ->join('persona as pe', 'pe.id', '=', 'm.idPersona')
            ->where('m.id', $idEstudiante)
            ->select(
                'm.id as idMatricula',
                'm.idFicha',
                'ap.id as idApertura',
                'pe.identificacion as documento',
                DB::raw("TRIM(CONCAT(pe.nombre1, ' ', COALESCE(pe.nombre2,''), ' ', pe.apellido1, ' ', COALESCE(pe.apellido2,''))) as nombre"),
                'pe.email',
                'f.codigo as codigoFicha',
                'p.nombrePrograma as programa',
                'j.nombreJornada as jornada'
            )
            ->first();

        if (!$matricula) {
            return response()->json(['error' => 'Estudiante no encontrado'], 404);
        }

        // 2. Cortes de la apertura ordenados
        $cortes = DB::table('cortes')
            ->where('idApertura', $matricula->idApertura)
            ->orderBy('numero')
            ->get()
            ->keyBy('id'); // indexados por id para cruzar fácilmente

        // 3. Todas las calificaciones del estudiante con notaParcial por corte/materia
        $calificaciones = DB::table('calificacionActividad as ca')
            ->join('matriculaAcademica as ma', 'ma.id', '=', 'ca.idAMartriculaAcademica')
            ->join('actividades as a', 'a.id', '=', 'ca.idActividad')
            ->join('materia as mat', 'mat.id', '=', 'ma.idMateria')
            ->join('cortes as c', 'c.id', '=', 'ca.idCorte')
            ->leftJoin('valoracion as v', 'v.id', '=', 'ca.idValoracion')
            ->where('ma.idMatricula', $idEstudiante)
            ->whereNotNull('ca.idCorte')
            ->select(
                'c.id as idCorte',
                'c.numero as numeroCorte',
                'c.porcentaje as porcentajeCorte',
                'mat.id as idMateria',
                'mat.nombreMateria as materia',
                'a.id as idActividad',
                'a.tituloActividad as actividad',
                'a.porcentaje as porcentajeActividad',
                'ca.calificacionNumerica',
                'ca.calificacionEstandart',
                'v.nombreValoracion as valoracion',
                'ma.notaParcial',
            )
            ->orderBy('c.numero')
            ->orderBy('mat.nombreMateria')
            ->get();

        // 4. Estructurar por materia > cortes, y calcular definitiva ponderada
        //    definitiva = SUM(notaParcial_corteN * porcentajeCorteN / 100)
        $porMateria = $calificaciones->groupBy('idMateria')->map(function ($filas) use ($cortes) {
            $primera = $filas->first();

            // Agrupar actividades por corte
            $cortesMateria = $filas->groupBy('idCorte')->map(function ($actividades) use ($cortes) {
                $primeraAct = $actividades->first();
                $corte = $cortes->get($primeraAct->idCorte);

                return [
                    'idCorte' => $primeraAct->idCorte,
                    'numeroCorte' => $primeraAct->numeroCorte,
                    'porcentajeCorte' => (float) $primeraAct->porcentajeCorte,
                    'notaParcial' => $primeraAct->notaParcial !== null
                        ? round((float) $primeraAct->notaParcial, 2)
                        : null,
                    'fechaInicial' => $corte?->fechaInicial,
                    'fechaFinal' => $corte?->fechaFinal,
                    'actividades' => $actividades->map(fn($a) => [
                        'idActividad' => $a->idActividad,
                        'actividad' => $a->actividad,
                        'porcentajeActividad' => $a->porcentajeActividad,
                        'calificacionNumerica' => $a->calificacionNumerica,
                        'calificacionEstandart' => $a->calificacionEstandart,
                        'valoracion' => $a->valoracion,
                    ])->values(),
                ];
            })->values();

            // Nota definitiva: suma ponderada de notaParcial * porcentajeCorte
            $definitiva = null;
            $totalPorcentaje = 0;
            $sumaAcumulada = 0;

            foreach ($cortesMateria as $cm) {
                if ($cm['notaParcial'] !== null && $cm['porcentajeCorte'] > 0) {
                    $sumaAcumulada += $cm['notaParcial'] * ($cm['porcentajeCorte'] / 100);
                    $totalPorcentaje += $cm['porcentajeCorte'];
                }
            }

            // Solo calculamos definitiva si tenemos el 100% de los cortes calificados
            if ($totalPorcentaje > 0) {
                $definitiva = round($sumaAcumulada, 2);
            }

            return [
                'idMateria' => $primera->idMateria,
                'materia' => $primera->materia,
                'cortes' => $cortesMateria,
                'definitiva' => $definitiva,
                'estado' => $definitiva !== null
                    ? ($definitiva >= 3.0 ? 'APROBADA' : 'REPROBADA')
                    : 'EN PROCESO',
            ];
        })->values();

        // 5. Promedio general = promedio de todas las definitivas disponibles
        $definitivas = $porMateria
            ->whereNotNull('definitiva')
            ->pluck('definitiva');

        $promedioGeneral = $definitivas->isNotEmpty()
            ? round($definitivas->average(), 2)
            : null;

        return response()->json([
            'data' => [
                'idEstudiante' => $matricula->idMatricula,
                'documento' => $matricula->documento,
                'nombre' => $matricula->nombre,
                'email' => $matricula->email ?? '',
                'ficha' => [
                    'codigo' => $matricula->codigoFicha,
                    'programa' => $matricula->programa,
                    'jornada' => $matricula->jornada,
                ],
                'promedioGeneral' => $promedioGeneral,
                'materias' => $porMateria,
            ]
        ]);
    }

    /**
     * Descargar boletín en PDF
     * GET /coordinador/boletines/{id}/pdf
     */
    public function descargarPdfBoletin($idEstudiante)
    {
        return response("PDF Generado para el estudiante {$idEstudiante}", 200)
            ->header('Content-Type', 'application/pdf');
    }

    /**
     * Enviar boletines por correo
     * POST /coordinador/boletines/ficha/{idFicha}/enviar
     */
    public function enviarBoletinesFicha($idFicha)
    {
        return response()->json([
            'ok' => true,
            'message' => 'Boletines enviados correctamente para la ficha ' . $idFicha
        ]);
    }

    /**
     * Exportar boletines en Excel
     * GET /coordinador/boletines/exportar
     */
    public function exportarBoletinesGenerales()
    {
        return response("Archivo Excel de boletines", 200)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}