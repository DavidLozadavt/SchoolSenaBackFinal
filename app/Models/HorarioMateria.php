<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AsignacionSesion;

class HorarioMateria extends Model
{
    use HasFactory;
    public static $snakeAttributes = false;
    public $timestamps = true;
    protected $table = 'horarioMateria';
    protected $guarded = ['id'];

    public function infraestructura(): BelongsTo
    {
        return $this->belongsTo(Infraestructura::class, 'idInfraestructura');
    }

    public function dia(): BelongsTo
    {
        return $this->belongsTo(Dia::class, 'idDia');
    }

    public function gradoMateria(): BelongsTo
    {
        return $this->belongsTo(GradoMateria::class, 'idGradoMateria');
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }

    public function sesionMaterias(): HasMany
    {
        return $this->hasMany(SesionMateria::class, 'idHorarioMateria', 'id');
    }

    public function asignacionSesion(): HasMany
    {
        return $this->hasMany(AsignacionSesion::class, 'idHorarioMateria', 'id');
    }

    public function detallesRmi(): HasMany
    {
        return $this->hasMany(DetalleRmi::class, 'idHorarioMateria', 'id');
    }

    public static function asignacionesEspecialesApi(self $horario): array
    {
        $asignaciones = $horario->relationLoaded('asignacionSesion')
            ? $horario->asignacionSesion
            : $horario->asignacionSesion()->with('contrato.persona')->get();

        return $asignaciones
            ->map(fn (AsignacionSesion $a) => $a->toAsignacionSesionApi())
            ->values()
            ->all();
    }

    public static function generarRmis(HorarioMateria $horario): void
    {
        $inicio = \Carbon\Carbon::parse($horario->fechaInicial)->startOfMonth();
        $fin    = \Carbon\Carbon::parse($horario->fechaFinal)->startOfMonth();

        $cursor = $inicio->copy();

        while ($cursor->lte($fin)) {
            $periodo = $cursor->format('Y-m');

            $rmi = Rmi::firstOrCreate(
                ['periodo' => $periodo],
                ['estado' => 'PENDIENTE', 'observacion' => null]
            );

            DetalleRmi::firstOrCreate(
                [
                    'idRmi'            => $rmi->id,
                    'idHorarioMateria' => $horario->id,
                ],
                [
                    'estado'      => 'PENDIENTE',
                    'observacion' => null,
                ]
            );

            $cursor->addMonth();
        }
    }

    /**
     * Duplica un horario para el instructor secundario de un horario compartido.
     */
    public static function duplicarParaAsignacionCompartida(AsignacionSesion $asignacion): ?self
    {
        $original = self::find($asignacion->idHorarioMateria);
        if (!$original || !$asignacion->idContrato) {
            return null;
        }

        $clon = self::where('idFicha', $original->idFicha)
            ->where('idGradoMateria', $original->idGradoMateria)
            ->where('idDia', $original->idDia)
            ->where('horaInicial', $original->horaInicial)
            ->where('horaFinal', $original->horaFinal)
            ->where('fechaInicial', $original->fechaInicial)
            ->where('idContrato', $asignacion->idContrato)
            ->where('id', '!=', $original->id)
            ->first();

        if (!$clon) {
            $clon = $original->replicate();
        }

        $clon->idContrato = $asignacion->idContrato;
        $clon->fechaInicial = $asignacion->fechaInicio;
        $clon->fechaFinal = $asignacion->fechaFin;
        $clon->estado = 'ASIGNADO';

        if ($asignacion->observacion) {
            $clon->observacion = $asignacion->observacion;
        }

        $clon->save();

        self::generarRmis($clon);

        return $clon;
    }
}
