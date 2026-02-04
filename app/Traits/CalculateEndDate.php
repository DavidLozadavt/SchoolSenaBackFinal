<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\HorarioMateria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait CalculateEndDate
{

    /**
     * Calculate end date to first schedule by rap
     * @param string $fechaInicial
     * @param int $diaSemana
     * @param int $totalHoras
     * @param string $horaInicial
     * @param string $horaFinal
     * @return string
     */
    private function calculateEndDateFirstSchedule(string $fechaInicial, int $diaSemana, int $totalHoras, string $horaInicial, string $horaFinal): string
    {
        date_default_timezone_set("America/Bogota");

        $fechaActual    = Carbon::parse($fechaInicial);
        $horaInicialObj = Carbon::parse($horaInicial);
        $horaFinalObj   = Carbon::parse($horaFinal);

        $horasPorDia = $horaInicialObj->diffInHours($horaFinalObj);

        while ($fechaActual->dayOfWeek !== $diaSemana) {
            $fechaActual->addDay();
        }

        $horasRestantes = $totalHoras;
        $fechaFinal = $fechaActual->copy();

        while ($horasRestantes > 0) {
            $horasEnElDia = min($horasPorDia, $horasRestantes);
            $horasRestantes -= $horasEnElDia;

            $fechaFinal = $fechaActual->copy()->setTimeFromTimeString($horaInicial)->addHours($horasEnElDia);

            if ($horasRestantes > 0) {
                $fechaActual->addWeek();
            }
        }

        return $fechaFinal->format('Y-m-d');
    }

    /**
     * Calculate end date schedule
     * @param string|int $idGradoMateria
     * @param string $fechaInicial
     * @param int $diaSemana
     * @param int $totalHorasRap
     * @param string $horaInicial
     * @param string $horaFinal
     * @return string
     */
    private function calculateEndDate(
        string|int $idGradoMateria,
        string $fechaInicial,
        int $diaSemana,
        int $totalHorasRap,
        string $horaInicial,
        string $horaFinal,
        string|null $idHorarioMateria = null,
    ): string {
        date_default_timezone_set("America/Bogota");

        $horarios = HorarioMateria::where('idGradoMateria', $idGradoMateria)
            ->when($idHorarioMateria, function ($query) use ($idHorarioMateria) {
                $query->where('id', '<>', $idHorarioMateria);
            })
            ->whereNotNull(['idDia', 'horaInicial', 'horaFinal'])
            ->orderBy('fechaInicial', 'asc')
            ->select('id', 'idDia', 'horaInicial', 'horaFinal', 'fechaInicial', 'fechaFinal', 'estado')
            ->get();

        if ($horarios->isEmpty()) {
            return $this->calculateEndDateFirstSchedule(
                $fechaInicial,
                $diaSemana,
                $totalHorasRap,
                $horaInicial,
                $horaFinal
            );
        }

        $schedules = $this->getFilteredDatesBySchedule(
            $horarios,
            $fechaInicial,
            $diaSemana,
            $horaInicial,
            $horaFinal
        );
        Log::info($schedules);

        $horasAcumuladas = 0;
        $lastSchedule = null;

        foreach ($schedules as $schedule) {
            $horasDisponibles = $schedule['diferenciaHoras'];

            // Si el horario ya existe y está en el día correcto, actualizamos su fecha final
            if (!is_null($schedule['idHorarioMateria'])) {
                HorarioMateria::where('id', $schedule['idHorarioMateria'])
                    ->update(['fechaFinal' => $schedule['fecha']]);
            }

            // Acumular horas para determinar el último horario válido
            $horasAcumuladas += $horasDisponibles;

            // Si ya alcanzamos el total de horas requeridas, terminamos
            if ($horasAcumuladas >= $totalHorasRap) {
                $lastSchedule = $schedule;
                break;
            }
        }

        Log::info($lastSchedule);

        // Retornar la fecha del último horario calculado
        return $lastSchedule ? $lastSchedule['fecha'] : null;
    }

    /**
     * Obtiene todas las fechas futuras de los horarios, incluyendo la fecha inicial del nuevo horario, ordenadas.
     *
     * @param \Illuminate\Support\Collection|array $horariosMaterias
     * @param string $fechaInicialNewHorario
     * @param string $idDiaNewHorario
     * @param string $horaInicialNewHorario
     * @param string $horaFinalNewHorario
     * @return \Illuminate\Support\Collection
     */
    private function getFilteredDatesBySchedule(
        Collection|array $horariosMaterias,
        string $fechaInicialNewHorario,
        string $idDiaNewHorario,
        string $horaInicialNewHorario,
        string $horaFinalNewHorario
    ): Collection {
        $schedulesMerged = collect();

        $fechaInicialNuevoHorarioCarbon = Carbon::parse($fechaInicialNewHorario);
        $horaInicialNuevoHorarioCarbon = Carbon::parse($horaInicialNewHorario);
        $horaFinalNuevoHorarioCarbon = Carbon::parse($horaFinalNewHorario);
        $diferenciaHorasNuevoHorario = $horaFinalNuevoHorarioCarbon->diffInHours($horaInicialNuevoHorarioCarbon);

        for ($i = 0; $i < 26; $i++) {
            $fecha = $fechaInicialNuevoHorarioCarbon->copy()->addWeeks($i);
            $schedulesMerged->push([
                'fecha' => $fecha->toDateString(),
                'idHorarioMateria' => null,
                'idDia' => $idDiaNewHorario,
                'diferenciaHoras' => $diferenciaHorasNuevoHorario,
                'fechaInicial' => $fechaInicialNewHorario,
            ]);
        }

        foreach ($horariosMaterias as $horario) {
            $fechaInicial = Carbon::parse($horario->fechaInicial);
            $horaInicial = Carbon::parse($horario->horaInicial);
            $horaFinal = Carbon::parse($horario->horaFinal);
            $diferenciaHoras = $horaFinal->diffInHours($horaInicial);
            $idHorarioMateria = $horario->id;
            $idDia = $horario->idDia;
            $fechaInicialHorario = $fechaInicial->toDateString();

            for ($i = 0; $i < 26; $i++) {
                $fecha = $fechaInicial->copy()->addWeeks($i);
                $schedulesMerged->push([
                    'fecha' => $fecha->toDateString(),
                    'idHorarioMateria' => $idHorarioMateria,
                    'idDia' => $idDia,
                    'diferenciaHoras' => $diferenciaHoras,
                    'fechaInicial' => $fechaInicialHorario,
                ]);
            }
        }

        $schedulesMerged = $schedulesMerged->sortBy('fecha')->values();

        $schedulesFiltered  = $schedulesMerged->unique(function ($item) {
            return $item['fecha'];
        })->values();

        return $schedulesFiltered;
    }
}
