<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asistencia;
use App\Models\MatriculaAcademica;
use App\Models\SesionMateria;
use App\Models\HorarioMateria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegistrarInasistenciasAutomaticas extends Command
{
    protected $signature = 'asistencia:registrar-inasistencias';

    protected $description = 'Registra automáticamente inasistencia (asistio=false) para estudiantes en clases EN CURSO que aún no tienen registro de asistencia';

    public function handle()
    {
        date_default_timezone_set('America/Bogota');

        $hoy = now();
        // Día de la semana: 1=Lunes ... 7=Domingo (formato de la BD)
        $dbIdDia = ($hoy->dayOfWeek == 0) ? 7 : $hoy->dayOfWeek;

        $this->info("Ejecutando registro de inasistencias automáticas...");
        $this->info("Fecha: {$hoy->toDateString()} | Día BD: {$dbIdDia}");

        // Buscar horarios cuya clase está EN CURSO hoy:
        // - La fecha actual está entre fechaInicial y fechaFinal
        // - El día de la semana coincide con idDia
        $horariosEnCurso = HorarioMateria::where('idDia', $dbIdDia)
            ->whereDate('fechaInicial', '<=', $hoy->toDateString())
            ->where(function ($query) use ($hoy) {
                $query->whereDate('fechaFinal', '>=', $hoy->toDateString())
                      ->orWhereNull('fechaFinal');
            })
            ->get();

        if ($horariosEnCurso->isEmpty()) {
            $this->info('No se encontraron clases EN CURSO hoy. Nada que hacer.');
            return 0;
        }

        $totalCreados = 0;

        foreach ($horariosEnCurso as $horarioMateria) {
            // Buscar la sesión existente para hoy en este horario
            $sesionMateria = SesionMateria::where('idHorarioMateria', $horarioMateria->id)
                ->whereDate('fechaSesion', $hoy->toDateString())
                ->first();

            if (!$sesionMateria) {
                $this->info("HorarioMateria #{$horarioMateria->id}: no tiene sesión para hoy, se omite.");
                continue;
            }

            // Obtener idMateria a través de gradoMateria
            $gradoMateria = DB::table('gradoMateria')
                ->where('id', $horarioMateria->idGradoMateria)
                ->first();

            if (!$gradoMateria) {
                continue;
            }

            $idFicha = $horarioMateria->idFicha;
            $idMateria = $gradoMateria->idMateria;

            // Obtener todas las matrículas académicas de esta ficha y materia
            $matriculas = MatriculaAcademica::where('idFicha', $idFicha)
                ->where('idMateria', $idMateria)
                ->get();

            foreach ($matriculas as $matricula) {
                // Solo crear si no existe ya un registro para esta sesión
                $existeAsistencia = Asistencia::where('idMatriculaAcademica', $matricula->id)
                    ->where('idSesionMateria', $sesionMateria->id)
                    ->exists();

                if (!$existeAsistencia) {
                    Asistencia::create([
                        'idMatriculaAcademica' => $matricula->id,
                        'idSesionMateria' => $sesionMateria->id,
                        'horaLLegada' => null,
                        'asistio' => false,
                    ]);
                    $totalCreados++;
                }
            }

            $this->info("HorarioMateria #{$horarioMateria->id} - Sesión #{$sesionMateria->id}: procesada.");
        }

        $this->info("Proceso finalizado. Total de inasistencias registradas: {$totalCreados}");

        Log::info('RegistrarInasistenciasAutomaticas ejecutado', [
            'fecha' => $hoy->toDateString(),
            'horarios_en_curso' => $horariosEnCurso->count(),
            'inasistencias_creadas' => $totalCreados,
        ]);

        return 0;
    }
}
