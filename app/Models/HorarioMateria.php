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
     * Duplica un horario para una asignación compartida.
     * Esto permite que el instructor secundario tenga su propio registro
     * para el seguimiento de RMI y sesiones.
     */
    public static function duplicarParaAsignacion(AsignacionSesion $asignacion)
    {
        $original = self::find($asignacion->idHorarioMateria);
        if (!$original) return null;

        // Intentar reciclar un horario existente para este mismo slot que no tenga contrato (placeholder)
        // Esto evita crear múltiples duplicados para el mismo bloque compartido
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
            // Si no hay ninguno para reciclar, lo replicamos del original
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

        // Generar RMIs para el nuevo contrato en este horario
        self::generarRmis($clon);

        return $clon;
    }
}
