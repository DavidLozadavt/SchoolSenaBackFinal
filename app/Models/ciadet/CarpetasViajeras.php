<?php

namespace App\Models\ciadet;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarpetasViajeras extends Model
{
    use HasFactory;

    protected $table = 'carpetas_viajeras';

    public $timestamps = false;

    protected $fillable = [
        'total',
        'nivel_academico',
        'modulo',
        'aprobada',
        'pago',
        'total_horas',
        'persona_id',
        'codigo_transferencia',
    ];

    protected $casts = [
        'total'    => 'decimal:2',
        'aprobada' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function persona()
    {
        return $this->belongsTo(Person::class, 'persona_id');
    }

    public function archivos()
    {
        return $this->hasMany(Archivos::class, 'carpetas_viajeras_id');
    }
}
