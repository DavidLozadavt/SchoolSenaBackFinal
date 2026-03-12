<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
