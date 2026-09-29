<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ficha;
use App\Models\Matricula;
use App\Models\NovedadesAprendiz;
use App\Models\NotificacionSistema;
use App\Jobs\SendBasicEmail;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InstructorLiderController extends Controller
{
    public function getFichasLider(Request $request)
    {
        $user = KeyUtil::user();

        $contrato = $user->persona->contrato->first();

        if (!$contrato) {
            return response()->json([
                'message' => 'El usuario no tiene contrato'
            ], 404);
        }

        $fichas = Ficha::where('idInstructorLider', $contrato->id)
            ->with(['asignacion.programa', 'jornada', 'sede'])
            ->get();

        return response()->json($fichas);
    }

    public function getAprendicesFicha(int $idFicha)
    {
        $tableMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : 'matriculaacademica';

        // Intentar obtener de matriculaAcademica primero
        $maQuery = DB::table($tableMa)
            ->where('idFicha', $idFicha)
            ->select('idMatricula', 'id')
            ->distinct();

        $ma = $maQuery->get();

        if ($ma->isEmpty()) {
            // Fallback a matricula directa
            $matriculasIds = DB::table('matricula')
                ->where('idFicha', $idFicha)
                ->whereIn('estado', ['ACTIVO', 'INACTIVO', 'OCULTO', 'PENDIENTE', 'RECHAZADO', 'APROBADO', 'CANCELADO', 'REPROBADO', 'CERRADO', 'ACEPTADO', 'LEIDO', 'EN ESPERA', 'INSCRIPCION', 'MATRICULADO', 'ABIERTO', 'EN CURSO', 'POR ACTUALIZAR', 'CURSANDO', 'ENTREVISTA', 'SIN ENTREVISTA', 'JUSTIFICADO', 'EN FORMACION', 'RETIRO VOLUNTARIO', 'POR EVALUAR', 'TRASLADADO', 'APLAZADO', 'DESERCION', 'CONDICIONADO'])
                ->pluck('id');

            if ($matriculasIds->isNotEmpty()) {
                $ma = DB::table($tableMa)
                    ->whereIn('idMatricula', $matriculasIds)
                    ->select('idMatricula', 'id')
                    ->distinct()
                    ->get();
            }
        }

        $idsMatricula = $ma->pluck('idMatricula')->unique()->filter()->values();

        $aprendices = DB::table('matricula as m')
            ->join('persona as p', 'm.idPersona', '=', 'p.id')
            ->leftJoin('usuario as u', 'p.id', '=', 'u.idpersona')
            ->whereIn('m.id', $idsMatricula)
            ->select([
                'm.id as idMatricula',
                'p.id as idPersona',
                'p.identificacion',
                DB::raw("TRIM(CONCAT(COALESCE(p.nombre1,''), ' ', COALESCE(p.nombre2,''), ' ', COALESCE(p.apellido1,''), ' ', COALESCE(p.apellido2,''))) as nombreCompleto"),
                'p.rutaFoto',
                'u.email',
                'm.estado as estadoMatricula'
            ])
            ->get();

        return response()->json($aprendices);
    }

    public function cambiarEstadoAprendiz(Request $request)
    {
        $request->validate([
            'idMatricula' => 'required|exists:matricula,id',
            'nuevoEstado' => 'required|string',
            'observacion' => 'nullable|string'
        ]);

        $user = KeyUtil::user();

        return DB::transaction(function () use ($request, $user) {
            $matricula = Matricula::findOrFail($request->idMatricula);

            // Guardar la novedad (testigo de quien hace el cambio)
            NovedadesAprendiz::create([
                'idusuario' => $user->id,
                'idmatricula' => $matricula->id,
                'cambio' => $request->nuevoEstado,
                'observacion' => $request->observacion
            ]);

            // Actualizar la matricula
            $matricula->estado = $request->nuevoEstado;
            $matricula->save();

            // Enviar notificación y correo al aprendiz
            $persona = $matricula->person;
            if ($persona) {
                $usuario = $persona->usuario;

                if ($usuario) {
                    // Notificación en el sistema
                    NotificacionSistema::create([
                        'fecha' => now()->toDateString(),
                        'hora' => now()->toTimeString(),
                        'asunto' => 'Cambio de estado de matrícula',
                        'mensaje' => "Su estado de matrícula ha sido cambiado a: " . $request->nuevoEstado .
                            ($request->observacion ? ". Observación: " . $request->observacion : ""),
                        'estado_id' => 1,
                        'idUsuarioReceptor' => $usuario->id,
                        'idUsuarioRemitente' => $user->id,
                        'idTipoNotificacion' => 1,
                        'idEmpresa' => KeyUtil::idCompany(),
                        'route' => '/'
                    ]);
                }

                // Correo opcional: nunca debe tumbar el cambio de estado ni la novedad.
                try {
                    $email = trim((string) ($persona->email ?? ''));
                    if ($email === '' && $usuario) {
                        $email = trim((string) ($usuario->email ?? ''));
                    }

                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $nombre = trim($persona->nombre1 . ' ' . $persona->apellido1);
                        $asunto = 'Actualización de Estado de Matrícula';
                        $mensaje = "Estimado(a) $nombre,\n\n"
                            . "Le informamos que el estado de su matrícula ha sido actualizado a: " . $request->nuevoEstado . ".\n\n"
                            . ($request->observacion ? "Observación: " . $request->observacion . "\n\n" : "")
                            . "Si tiene alguna inquietud, puede comunicarse con el equipo administrativo.\n\n"
                            . "Atentamente,\n"
                            . "Equipo Administrativo\n"
                            . "Sistema de Gestión Académica";

                        SendBasicEmail::dispatch($email, $asunto, $mensaje);
                    } else {
                        Log::info('Cambio de estado de aprendiz sin correo válido; se omite el envío', [
                            'idMatricula' => $matricula->id,
                            'idPersona' => $persona->id ?? null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Fallo al enviar correo de cambio de estado de aprendiz; el estado ya fue guardado', [
                        'idMatricula' => $matricula->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Estado del aprendiz actualizado correctamente',
                'data' => $matricula
            ]);
        });
    }
    
}
