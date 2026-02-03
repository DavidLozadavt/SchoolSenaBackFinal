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
}
