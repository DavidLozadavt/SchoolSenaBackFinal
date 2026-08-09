<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Mail\MailService;
use App\Models\NotificacionSistema;
use App\Models\TipoNotificacion;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * E3: Calificación de actividades individual o por grupo con réplica.
 */
class CalificacionActividadController extends Controller
{
    /** Marca interna en `ComentarioDocente` cuando el instructor solicita corrección sin migraciones. Debe iniciar el texto guardado en BD. */
    public const MARCA_SOLICITUD_CORRECCION = '[SOLICITUD_CORRECCIÓN]';

    public static function comentarioIndicaCorreccionPendiente(?string $texto): bool
    {
        $t = trim((string) $texto);

        return $t !== '' && str_starts_with($t, self::MARCA_SOLICITUD_CORRECCION);
    }

    /**
     * Descargar el archivo de entrega (forzar attachment).
     * Autorización mínima: el instructor que asignó (ca.idPersona).
     */
    public function descargarArchivo(int $idCalificacionActividad)
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersona = $user?->idpersona ?? null;
            if (!$idPersona) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $row = DB::table('calificacionActividad')
                ->where('id', $idCalificacionActividad)
                ->select('id', 'archivo', 'idPersona')
                ->first();

            if (!$row) {
                return response()->json(['error' => 'Entrega no encontrada'], 404);
            }

            if ((int) $row->idPersona !== (int) $idPersona) {
                return response()->json(['error' => 'No tienes permiso para descargar este archivo'], 403);
            }

            $archivo = trim((string) ($row->archivo ?? ''));
            if ($archivo === '') {
                return response()->json(['error' => 'La entrega no tiene archivo adjunto'], 404);
            }

            if (!Storage::disk('public')->exists($archivo)) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }

            $fileName = basename($archivo);
            $absolutePath = Storage::disk('public')->path($archivo);
            $mime = Storage::disk('public')->mimeType($archivo) ?: 'application/octet-stream';

            return response()->download($absolutePath, $fileName, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * E3-HU1: Calificar actividad de forma individual por aprendiz.
     * Actividades con evidencia: solo se permite calificar si el aprendiz adjuntó evidencia (archivo o comentario).
     * Actividades sin evidencia: se puede calificar sin evidencia.
     */
    public function calificarIndividual(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idCalificacionActividad' => 'required|integer',
                'calificacionNumerica' => 'required|numeric|min:0',
                'ComentarioDocente' => 'nullable|string|max:2000',
            ]);

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $ca = DB::table('calificacionActividad')
                ->where('id', $validated['idCalificacionActividad'])
                ->first();
            if (!$ca) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }

            $actividad = DB::table('actividades')->where('id', $ca->idActividad)->first();
            if ($actividad && ($actividad->tipoActividad ?? '') === 'con evidencia') {
                $tieneEvidencia = !empty(trim($ca->archivo ?? '')) || !empty(trim($ca->ComentarioEstudiante ?? ''));
                if (!$tieneEvidencia) {
                    return response()->json([
                        'error' => 'Las actividades con evidencia requieren que el aprendiz adjunte una evidencia (archivo o enlace) antes de poder calificar.',
                    ], 422);
                }
            }

            $actualizado = DB::table('calificacionActividad')
                ->where('id', $validated['idCalificacionActividad'])
                ->update([
                    'calificacionNumerica' => (string) $validated['calificacionNumerica'],
                    'ComentarioDocente' => $validated['ComentarioDocente'] ?? null,
                    'fechaCalificacion' => now(),
                    'updated_at' => now(),
                ]);

            if (!$actualizado) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }

            return response()->json(['message' => 'Calificación registrada']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * E3-HU2: Calificar actividad por grupo y replicar a todos los integrantes.
     * Actividades con evidencia: solo se califican los que tienen evidencia; si alguno no tiene, se rechaza todo.
     */
    public function calificarPorGrupo(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idActividad' => 'required|exists:actividades,id',
                'idGrupo' => 'required|exists:grupos,id',
                'calificacionNumerica' => 'required|numeric|min:0',
                'ComentarioDocente' => 'nullable|string|max:2000',
            ]);

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $actividad = DB::table('actividades')->where('id', $validated['idActividad'])->first();
            $registros = DB::table('calificacionActividad')
                ->where('idActividad', $validated['idActividad'])
                ->where('idGrupo', $validated['idGrupo'])
                ->get();

            if ($actividad && ($actividad->tipoActividad ?? '') === 'con evidencia') {
                $alMenosUnoConEvidencia = false;
                foreach ($registros as $r) {
                    $tieneEvidencia = !empty(trim($r->archivo ?? '')) || !empty(trim($r->ComentarioEstudiante ?? ''));
                    if ($tieneEvidencia) {
                        $alMenosUnoConEvidencia = true;
                        break;
                    }
                }
                if (!$alMenosUnoConEvidencia) {
                    return response()->json([
                        'error' => 'Las actividades con evidencia requieren que al menos un integrante del grupo haya realizado la entrega antes de calificar.',
                    ], 422);
                }
            }

            $count = 0;
            foreach ($registros as $r) {
                DB::table('calificacionActividad')
                    ->where('id', $r->id)
                    ->update([
                        'calificacionNumerica' => (string) $validated['calificacionNumerica'],
                        'ComentarioDocente' => $validated['ComentarioDocente'] ?? null,
                        'fechaCalificacion' => now(),
                        'updated_at' => now(),
                    ]);
                $count++;
            }

            return response()->json([
                'message' => "Calificación aplicada a {$count} integrante(s)",
                'integrantesAfectados' => $count,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar aprendices asignados a una actividad para una ficha.
     * Incluye: foto, identificación, entrega (archivo, comentario), estado, nota.
     */
    public function listarPorActividad(int $idActividad, int $idFicha): JsonResponse
    {
        try {
            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['data' => []]);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : 'idFicha');

            $calificaciones = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->leftJoin('persona as p', 'm.idPersona', '=', 'p.id')
                ->leftJoin('usuario as u', 'u.idpersona', '=', 'm.idPersona')
                ->leftJoin('grupos as g', 'ca.idGrupo', '=', 'g.id')
                ->where('ca.idActividad', $idActividad)
                ->where('ma.' . $colFicha, $idFicha)
                ->select([
                    'ca.id as idCalificacionActividad',
                    'ca.idAMartriculaAcademica',
                    'ca.idGrupo',
                    'g.nombreGrupo',
                    'ca.calificacionNumerica',
                    'ca.calificacionEstandart',
                    'ca.ComentarioDocente',
                    'ca.ComentarioEstudiante',
                    'ca.archivo',
                    'ca.fechaFinal',
                    'ca.fechaCalificacion',
                    'ca.updated_at',
                    'm.id as idMatricula',
                    'p.identificacion',
                    'p.rutaFoto',
                    'u.email as emailUsuario',
                    DB::raw("CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.nombre2,''), ' ', COALESCE(p.apellido1,''), ' ', COALESCE(p.apellido2,'')) as nombreAprendiz"),
                ])
                ->orderBy('ca.id', 'desc')
                ->get();

            $vistos = [];
            $result = [];
            foreach ($calificaciones as $c) {
                $idMat = $c->idMatricula ?? null;
                if ($idMat !== null && isset($vistos[$idMat])) {
                    continue;
                }
                $vistos[$idMat] = true;

                $entregado = !empty(trim($c->ComentarioEstudiante ?? '')) || !empty(trim($c->archivo ?? ''));
                $calificado = $c->calificacionNumerica !== null && $c->calificacionNumerica !== '';
                $solicitudCorreccion = self::comentarioIndicaCorreccionPendiente($c->ComentarioDocente ?? null)
                    && ! $calificado;
                $estado = $calificado
                    ? 'CALIFICADO'
                    : ($solicitudCorreccion
                        ? 'CORRECCION_SOLICITADA'
                        : ($entregado ? 'ENVIADO' : 'PENDIENTE'));

                $result[] = [
                    'idCalificacionActividad' => $c->idCalificacionActividad,
                    'idAMartriculaAcademica' => $c->idAMartriculaAcademica,
                    'idMatricula' => $c->idMatricula,
                    'idGrupo' => $c->idGrupo,
                    'nombreGrupo' => $c->nombreGrupo ?? null,
                    'nombreAprendiz' => trim($c->nombreAprendiz ?? '') ?: 'Sin nombre',
                    'identificacion' => $c->identificacion ?? '',
                    'email' => $c->emailUsuario ?? null,
                    'rutaFoto' => $c->rutaFoto ?? null,
                    'calificacionNumerica' => $c->calificacionNumerica,
                    'calificacionEstandart' => $c->calificacionEstandart,
                    'ComentarioDocente' => $c->ComentarioDocente,
                    'ComentarioEstudiante' => $c->ComentarioEstudiante,
                    'archivo' => $c->archivo,
                    'fechaFinal' => $c->fechaFinal,
                    'fechaCalificacion' => $c->fechaCalificacion,
                    /** Útil como referencia de última modificación del registro (p. ej. tras entrega). */
                    'fechaActualizacionRegistro' => $c->updated_at ?? null,
                    'estado' => $estado,
                ];
            }

            return response()->json(['data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Instructor: amplía solo `fechaFinal` del registro de calificación de un aprendiz (sin tocar otros asignados).
     * No marca corrección en `ComentarioDocente`. No usa el endpoint masivo `ampliar()`.
     * Tras guardar exitosamente, notifica al aprendiz (plataforma + correo).
     */
    public function ampliarPlazoIndividual(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idCalificacionActividad' => 'required|integer',
                'fechaLimite' => 'required|date',
                'descripcionExtension' => 'nullable|string|max:2000',
            ]);

            if (! Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersonaInstructor = $user?->idpersona ?? null;
            if (! $idPersonaInstructor) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $row = DB::table('calificacionActividad as ca')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->where('ca.id', $validated['idCalificacionActividad'])
                ->select([
                    'ca.id',
                    'ca.idPersona',
                    'ca.fechaFinal',
                    'ca.calificacionNumerica',
                    'a.tituloActividad',
                    'a.tipoActividad',
                ])
                ->first();

            if (! $row) {
                return response()->json(['error' => 'Entrega no encontrada'], 404);
            }

            if ((int) $row->idPersona !== (int) $idPersonaInstructor) {
                return response()->json(['error' => 'No tienes permiso para ampliar el plazo de esta entrega'], 403);
            }

            if (strtolower(trim((string) ($row->tipoActividad ?? ''))) === 'cuestionario') {
                return response()->json(['error' => 'La ampliación individual no aplica a cuestionarios.'], 422);
            }

            $calificado = $row->calificacionNumerica !== null && trim((string) $row->calificacionNumerica) !== '';
            if ($calificado) {
                return response()->json(['error' => 'No se puede ampliar el plazo de una entrega ya calificada.'], 422);
            }

            $tz = config('app.timezone');
            $nuevaFecha = Carbon::parse((string) $validated['fechaLimite'], $tz);
            $fechaFinalActual = $row->fechaFinal ? Carbon::parse((string) $row->fechaFinal, $tz) : null;
            $anteriorFmt = $fechaFinalActual ? $fechaFinalActual->format('Y-m-d H:i:s') : null;
            $nuevaFmt = $nuevaFecha->format('Y-m-d H:i:s');
            $fechaCambiada = $anteriorFmt === null || $nuevaFmt !== $anteriorFmt;

            $obs = trim((string) ($validated['descripcionExtension'] ?? ''));
            $observacionAmpliacion = Str::limit('[AMPLIACIÓN_INDIVIDUAL]' . ($obs !== '' ? ' ' . $obs : ''), 250);

            DB::transaction(function () use ($validated, $nuevaFecha, $fechaCambiada, $observacionAmpliacion) {
                DB::table('calificacionActividad')
                    ->where('id', $validated['idCalificacionActividad'])
                    ->update([
                        'fechaFinal' => $nuevaFecha->format('Y-m-d H:i:s'),
                        'updated_at' => now(),
                    ]);

                if ($fechaCambiada && Schema::hasTable('ampliacionActividad')) {
                    DB::table('ampliacionActividad')->insert([
                        'idCalificacionActividad' => $validated['idCalificacionActividad'],
                        'observacion' => $observacionAmpliacion,
                        'fechaExtendida' => $nuevaFecha->format('Y-m-d H:i:s'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            if ($fechaCambiada) {
                $this->notificarAmpliacionActividad(
                    [(int) $validated['idCalificacionActividad']],
                    $nuevaFecha
                );
            }

            return response()->json([
                'message' => 'Plazo individual actualizado correctamente.',
                'fechaFinal' => $nuevaFecha->format('Y-m-d H:i:s'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Instructor: solicita corrección de la entrega de un aprendiz (sin calificar).
     * Sin migraciones: usa prefijo en `ComentarioDocente` y opcionalmente extiende `fechaFinal` solo en esta fila.
     * No utiliza `ampliar()` masivo; no modifica otras asignaciones.
     * Si ya estaba en corrección, permite actualizar fecha y/o motivo sin error.
     */
    public function solicitarCorreccion(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idCalificacionActividad' => 'required|integer',
                'comentario' => 'nullable|string|max:2000',
                'tiempoExtra' => 'nullable|integer|min:1|max:365',
                'unidadTiempo' => 'nullable|in:horas,dias',
                /** Fecha y hora (p. ej. desde `datetime-local`); tiene prioridad sobre `fechaLimiteCorreccion` al día natural. */
                'fechaLimite' => 'nullable|date',
                'fechaLimiteCorreccion' => 'nullable|date',
            ]);

            if (! Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $user = KeyUtil::user();
            $idPersonaInstructor = $user?->idpersona ?? null;
            if (! $idPersonaInstructor) {
                return response()->json(['error' => 'Usuario autenticado sin persona asociada'], 401);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $tz = config('app.timezone');

            $row = DB::table('calificacionActividad as ca')
                ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
                ->where('ca.id', $validated['idCalificacionActividad'])
                ->select([
                    'ca.id',
                    'ca.idPersona',
                    'ca.idActividad',
                    'ca.archivo',
                    'ca.ComentarioEstudiante',
                    'ca.ComentarioDocente',
                    'ca.calificacionNumerica',
                    'ca.fechaFinal',
                    'a.tituloActividad',
                    'a.tipoActividad',
                ])
                ->first();

            if (! $row) {
                return response()->json(['error' => 'Entrega no encontrada'], 404);
            }

            if ((int) $row->idPersona !== (int) $idPersonaInstructor) {
                return response()->json(['error' => 'No tienes permiso para solicitar corrección en esta entrega'], 403);
            }

            $tipo = strtolower(trim((string) ($row->tipoActividad ?? '')));
            if ($tipo === 'cuestionario') {
                return response()->json(['error' => 'La corrección por entrega no aplica a cuestionarios.'], 422);
            }

            $calificado = $row->calificacionNumerica !== null && trim((string) $row->calificacionNumerica) !== '';
            if ($calificado) {
                return response()->json(['error' => 'No se puede solicitar corrección en una entrega ya calificada.'], 422);
            }

            $yaEnCorreccion = self::comentarioIndicaCorreccionPendiente($row->ComentarioDocente ?? null);

            $entregado = ! empty(trim((string) ($row->ComentarioEstudiante ?? '')))
                || ! empty(trim((string) ($row->archivo ?? '')));
            if (! $yaEnCorreccion && ! $entregado) {
                return response()->json(['error' => 'El aprendiz aún no ha entregado evidencia.'], 422);
            }

            $now = Carbon::now($tz);
            $fechaFinalActual = $row->fechaFinal ? Carbon::parse((string) $row->fechaFinal, $tz) : null;
            $plazoVencido = $fechaFinalActual && $now->greaterThan($fechaFinalActual);

            $tieneTiempoExtra = ! empty($validated['tiempoExtra']) && ! empty($validated['unidadTiempo']);
            $tieneFechaLimite = ! empty($validated['fechaLimite'] ?? null);
            $tieneFechaCorreccionDia = ! empty($validated['fechaLimiteCorreccion'] ?? null);

            if ($plazoVencido && ! $tieneTiempoExtra && ! $tieneFechaLimite && ! $tieneFechaCorreccionDia) {
                return response()->json([
                    'error' => 'El plazo de entrega ya venció. Indique tiempo extra (horas o días) o una nueva fecha límite.',
                ], 422);
            }

            $nuevaFecha = $fechaFinalActual ? $fechaFinalActual->copy() : $now->copy();
            $aplicarCambioFecha = false;

            if ($tieneFechaLimite) {
                $nuevaFecha = Carbon::parse((string) $validated['fechaLimite'], $tz);
                $aplicarCambioFecha = true;
            } elseif ($tieneFechaCorreccionDia) {
                $nuevaFecha = Carbon::parse((string) $validated['fechaLimiteCorreccion'], $tz)->endOfDay();
                $aplicarCambioFecha = true;
            } elseif ($tieneTiempoExtra) {
                $base = $fechaFinalActual ? $now->max($fechaFinalActual) : $now->copy();
                $n = (int) $validated['tiempoExtra'];
                if ($validated['unidadTiempo'] === 'dias') {
                    $nuevaFecha = $base->copy()->addDays($n)->endOfDay();
                } else {
                    $nuevaFecha = $base->copy()->addHours($n);
                }
                $aplicarCambioFecha = true;
            }

            $textoMotivo = trim((string) ($validated['comentario'] ?? ''));
            if ($yaEnCorreccion) {
                $comentarioGuardado = $textoMotivo !== ''
                    ? self::MARCA_SOLICITUD_CORRECCION . "\n" . $textoMotivo
                    : (string) ($row->ComentarioDocente ?? self::MARCA_SOLICITUD_CORRECCION);
            } else {
                $comentarioGuardado = $textoMotivo !== ''
                    ? self::MARCA_SOLICITUD_CORRECCION . "\n" . $textoMotivo
                    : self::MARCA_SOLICITUD_CORRECCION;
            }

            $idUsuarioReceptor = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
                ->join('usuario as u', 'u.idpersona', '=', 'm.idPersona')
                ->where('ca.id', $validated['idCalificacionActividad'])
                ->value('u.id');

            if (! $idUsuarioReceptor) {
                return response()->json(['error' => 'No se pudo resolver el usuario del aprendiz.'], 422);
            }

            $idRemitente = auth()->id();
            if (! $idRemitente) {
                return response()->json(['error' => 'Sesión inválida'], 401);
            }

            $titulo = (string) ($row->tituloActividad ?? 'Actividad');
            if ($yaEnCorreccion) {
                $msgNotif = $aplicarCambioFecha
                    ? ('El instructor amplió el plazo para corregir la actividad: ' . $titulo . '. Nueva fecha límite: ' . $nuevaFecha->format('Y-m-d H:i') . '.')
                    : ('El instructor actualizó las observaciones de corrección de la actividad: ' . $titulo . '.');
            } else {
                $msgNotif = 'El instructor solicitó corregir la actividad: ' . $titulo . '.';
                if ($aplicarCambioFecha) {
                    $msgNotif .= ' Nueva fecha límite: ' . $nuevaFecha->format('Y-m-d H:i') . '.';
                }
            }

            $anteriorFmt = $fechaFinalActual ? $fechaFinalActual->format('Y-m-d H:i:s') : null;
            $nuevaFmt = $nuevaFecha->format('Y-m-d H:i:s');
            $fechaCambiada = $aplicarCambioFecha
                && ($anteriorFmt === null || $nuevaFmt !== $anteriorFmt);

            $observacionAmpliacion = Str::limit(
                $textoMotivo !== ''
                    ? self::MARCA_SOLICITUD_CORRECCION . ' ' . $textoMotivo
                    : self::MARCA_SOLICITUD_CORRECCION,
                250
            );

            $enviarNotif = ! $yaEnCorreccion || $aplicarCambioFecha || $textoMotivo !== '';

            DB::transaction(function () use ($validated, $comentarioGuardado, $nuevaFecha, $idUsuarioReceptor, $idRemitente, $msgNotif, $fechaCambiada, $aplicarCambioFecha, $observacionAmpliacion, $enviarNotif) {
                $payload = [
                    'ComentarioDocente' => $comentarioGuardado,
                    'updated_at' => now(),
                ];
                if ($aplicarCambioFecha) {
                    $payload['fechaFinal'] = $nuevaFecha->format('Y-m-d H:i:s');
                }

                DB::table('calificacionActividad')
                    ->where('id', $validated['idCalificacionActividad'])
                    ->update($payload);

                if ($fechaCambiada && Schema::hasTable('ampliacionActividad')) {
                    DB::table('ampliacionActividad')->insert([
                        'idCalificacionActividad' => $validated['idCalificacionActividad'],
                        'observacion' => $observacionAmpliacion,
                        'fechaExtendida' => $nuevaFecha->format('Y-m-d H:i:s'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($enviarNotif) {
                    NotificacionSistema::create([
                        'fecha' => now()->toDateString(),
                        'hora' => now()->toTimeString(),
                        'asunto' => 'Corrección de actividad',
                        'mensaje' => $msgNotif,
                        'estado_id' => 1,
                        'idUsuarioReceptor' => (int) $idUsuarioReceptor,
                        'idUsuarioRemitente' => (int) $idRemitente,
                        'idTipoNotificacion' => TipoNotificacion::ID_ACTIVO,
                        'idEmpresa' => KeyUtil::idCompany(),
                        'route' => '/ambiente-virtual/actividades',
                    ]);
                }
            });

            return response()->json([
                'message' => 'Actividad enviada a corrección correctamente.',
                'fechaFinal' => $aplicarCambioFecha
                    ? $nuevaFecha->format('Y-m-d H:i:s')
                    : ($row->fechaFinal ?? null),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ampliar actividad: actualizar SOLO fechaFinal en las calificaciones de esta actividad para esta ficha.
     * No afecta otras actividades. El estado se calcula por fecha inicio, fecha límite y hora actual.
     * Tras guardar exitosamente, notifica a todos los aprendices asignados (plataforma + correo).
     */
    public function ampliar(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idActividad' => 'required|integer|exists:actividades,id',
                'idFicha' => 'required|integer',
                'fechaFinal' => 'required|date',
                'descripcionExtension' => 'nullable|string|max:2000',
            ]);

            if (!Schema::hasTable('calificacionActividad')) {
                return response()->json(['error' => 'Tabla no disponible'], 500);
            }

            $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
            $colFicha = Schema::hasColumn($tableMa, 'idFicha') ? 'idFicha' : (Schema::hasColumn($tableMa, 'idAsignacionPeriodoProgramaJornada') ? 'idAsignacionPeriodoProgramaJornada' : 'idFicha');

            $idsCa = DB::table('calificacionActividad as ca')
                ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                ->where('ca.idActividad', $validated['idActividad'])
                ->where('ma.' . $colFicha, $validated['idFicha'])
                ->pluck('ca.id');

            if ($idsCa->isEmpty()) {
                return response()->json(['error' => 'No hay asignaciones para esta actividad en esta ficha'], 404);
            }

            $tz = config('app.timezone');
            $fechaFin = Carbon::parse((string) $validated['fechaFinal'], $tz);
            $observacion = \Illuminate\Support\Str::limit($validated['descripcionExtension'] ?? '', 250);

            DB::transaction(function () use ($idsCa, $fechaFin, $observacion) {
                // Solo actualizar las calificaciones de ESTA actividad en ESTA ficha
                DB::table('calificacionActividad')
                    ->whereIn('id', $idsCa->all())
                    ->update([
                        'fechaFinal' => $fechaFin->format('Y-m-d H:i:s'),
                        'updated_at' => now(),
                    ]);

                if (Schema::hasTable('ampliacionActividad')) {
                    foreach ($idsCa as $idCa) {
                        DB::table('ampliacionActividad')->insert([
                            'idCalificacionActividad' => $idCa,
                            'observacion' => $observacion ?: null,
                            'fechaExtendida' => $fechaFin->format('Y-m-d H:i:s'),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            $this->notificarAmpliacionActividad($idsCa->map(fn ($id) => (int) $id)->all(), $fechaFin);

            return response()->json([
                'message' => 'Actividad ampliada correctamente',
                'registrosActualizados' => $idsCa->count(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Notifica ampliación de plazo (in-app + correo) a los aprendices de las calificaciones dadas.
     * Se invoca solo después de persistir la ampliación. Deduplica por usuario receptor.
     *
     * @param  array<int>  $idsCalificacionActividad
     */
    private function notificarAmpliacionActividad(array $idsCalificacionActividad, Carbon $nuevaFecha): void
    {
        $idsCalificacionActividad = array_values(array_unique(array_filter(array_map('intval', $idsCalificacionActividad))));
        if ($idsCalificacionActividad === []) {
            return;
        }

        $idRemitente = auth()->id();
        if (! $idRemitente) {
            return;
        }

        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';
        if (! Schema::hasTable($tableMa) || ! Schema::hasTable('matricula') || ! Schema::hasTable('usuario')) {
            return;
        }

        $destinatarios = DB::table('calificacionActividad as ca')
            ->join('actividades as a', 'ca.idActividad', '=', 'a.id')
            ->join($tableMa . ' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
            ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
            ->join('persona as p', 'm.idPersona', '=', 'p.id')
            ->join('usuario as u', 'u.idpersona', '=', 'm.idPersona')
            ->leftJoin('materia as mat', 'a.idMateria', '=', 'mat.id')
            ->leftJoin('persona as p_inst', 'ca.idPersona', '=', 'p_inst.id')
            ->whereIn('ca.id', $idsCalificacionActividad)
            ->select([
                'u.id as idUsuario',
                'p.email',
                'p.nombre1',
                'p.apellido1',
                'a.tituloActividad',
                'mat.nombreMateria',
                DB::raw("TRIM(CONCAT(COALESCE(p_inst.nombre1,''), ' ', COALESCE(p_inst.apellido1,''))) as nombreInstructor"),
            ])
            ->get();

        $fechaLegible = $this->formatearFechaAmpliacion($nuevaFecha);
        $idEmpresa = KeyUtil::idCompany();
        $yaNotificados = [];

        foreach ($destinatarios as $dest) {
            $idUsuario = (int) ($dest->idUsuario ?? 0);
            if ($idUsuario <= 0 || isset($yaNotificados[$idUsuario])) {
                continue;
            }
            $yaNotificados[$idUsuario] = true;

            $titulo = trim((string) ($dest->tituloActividad ?? 'Actividad')) ?: 'Actividad';
            $curso = trim((string) ($dest->nombreMateria ?? ''));
            $instructor = trim((string) ($dest->nombreInstructor ?? ''));
            $nombreEstudiante = trim(
                trim((string) ($dest->nombre1 ?? '')) . ' ' . trim((string) ($dest->apellido1 ?? ''))
            ) ?: 'estudiante';

            $msgNotif = 'La fecha de entrega de la actividad "' . $titulo . '" ha sido ampliada. Nueva fecha de entrega: ' . $fechaLegible . '.';
            if ($curso !== '') {
                $msgNotif .= ' Curso: ' . $curso . '.';
            }

            try {
                NotificacionSistema::create([
                    'fecha' => now()->toDateString(),
                    'hora' => now()->toTimeString(),
                    'asunto' => 'Actividad ampliada',
                    'mensaje' => $msgNotif,
                    'estado_id' => 1,
                    'idUsuarioReceptor' => $idUsuario,
                    'idUsuarioRemitente' => (int) $idRemitente,
                    'idTipoNotificacion' => TipoNotificacion::ID_ACTIVO,
                    'idEmpresa' => $idEmpresa,
                    'route' => '/ambiente-virtual/actividades',
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo crear notificación de ampliación', [
                    'idUsuario' => $idUsuario,
                    'error' => $e->getMessage(),
                ]);
            }

            $email = trim((string) ($dest->email ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $mensajeCorreo = "Hola, {$nombreEstudiante}.\n\n";
            $mensajeCorreo .= "Te informamos que la fecha de entrega de la siguiente actividad ha sido ampliada.\n\n";
            $mensajeCorreo .= "Actividad\n{$titulo}\n\n";
            if ($instructor !== '') {
                $mensajeCorreo .= "Instructor\n{$instructor}\n\n";
            }
            $mensajeCorreo .= "Nueva fecha de entrega\n{$fechaLegible}\n\n";
            $mensajeCorreo .= "Ahora dispones de tiempo adicional para realizar la entrega.\n\n";
            $mensajeCorreo .= "Ingresa a la plataforma para revisar la actividad.";

            try {
                Mail::to($email)->send(new MailService('Tu actividad ha sido ampliada', $mensajeCorreo));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar correo de ampliación', [
                    'email' => $email,
                    'idUsuario' => $idUsuario,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function formatearFechaAmpliacion(Carbon $fecha): string
    {
        try {
            return $fecha->copy()->locale('es')->isoFormat('D [de] MMMM [de] YYYY - h:mm a');
        } catch (\Throwable $e) {
            return $fecha->format('d/m/Y H:i');
        }
    }
}
