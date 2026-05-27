<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AsignacionSesion;
use App\Models\HorarioCompartido;

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

    /** Reemplazos de clase vinculados a este horario (tabla reemplazo). */
    public function asignacionSesion(): HasMany
    {
        return $this->hasMany(AsignacionSesion::class, 'idHorarioMateria', 'id');
    }

    /** Horarios compartidos donde este registro es el horario base (titular). */
    public function horariosCompartidos(): HasMany
    {
        return $this->hasMany(HorarioCompartido::class, 'idHorarioMateria', 'id');
    }

    public function detallesRmi(): HasMany
    {
        return $this->hasMany(DetalleRmi::class, 'idHorarioMateria', 'id');
    }

    public static function asignacionesEspecialesApi(self $horario): array
    {
        $reemplazos = ($horario->relationLoaded('asignacionSesion')
            ? $horario->asignacionSesion
            : $horario->asignacionSesion()->with('contrato.persona')->get()
        )->map(fn (AsignacionSesion $r) => $r->toAsignacionSesionApi());

        $compartidos = ($horario->relationLoaded('horariosCompartidos')
            ? $horario->horariosCompartidos
            : $horario->horariosCompartidos()->with('contratoSecundario.persona')->get()
        )->map(fn (HorarioCompartido $c) => $c->toAsignacionSesionApi());

        return $reemplazos->concat($compartidos)->values()->all();
    }

    // En HorarioMateria.php - ejecutar al crear/actualizar un horario
    public static function generarRmis(HorarioMateria $horario): void
    {
        $inicio = \Carbon\Carbon::parse($horario->fechaInicial)->startOfMonth();
        $fin    = \Carbon\Carbon::parse($horario->fechaFinal)->startOfMonth();

        $cursor = $inicio->copy();

        while ($cursor->lte($fin)) {
            $periodo = $cursor->format('Y-m');

            // Busca o crea el RMI para ese periodo
            $rmi = Rmi::firstOrCreate(
                ['periodo' => $periodo],
                ['estado' => 'PENDIENTE', 'observacion' => null]
            );

            // Crea el detalle si no existe
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
    public static function duplicarParaHorarioCompartido(HorarioCompartido $compartido): ?self
    {
        $original = self::find($compartido->idHorarioMateria);
        if (!$original || !$compartido->idContratoSecundario) {
            return null;
        }

        $clon = self::where('idFicha', $original->idFicha)
            ->where('idGradoMateria', $original->idGradoMateria)
            ->where('idDia', $original->idDia)
            ->where('horaInicial', $original->horaInicial)
            ->where('horaFinal', $original->horaFinal)
            ->where('fechaInicial', $original->fechaInicial)
            ->whereNull('idContrato')
            ->where('id', '!=', $original->id)
            ->first();

        if (!$clon) {
            $clon = $original->replicate();
        }

        $clon->idContrato = $compartido->idContratoSecundario;
        $clon->fechaInicial = $compartido->fechaInicial;
        $clon->fechaFinal = $compartido->fechaFinal;
        $clon->estado = 'ASIGNADO';

        if ($compartido->observacion) {
            $clon->observacion = $compartido->observacion;
        }

        $clon->save();

        self::generarRmis($clon);

        return $clon;
    }
}
