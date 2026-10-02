<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsistenciaActa extends Model
{
    use HasFactory;

    protected $table = 'asistenciaActa';

    protected $fillable = [
        'dependencia',
        'aprueba',
        'observacion',
        'idActa',
        'idContrato',
    ];

    /**
     * Casts de todos los campos
     */
    protected $casts = [
        'id' => 'integer',
        'dependencia' => 'string',
        'aprueba' => 'string', // SI / NO
        'observacion' => 'string',
        'idActa' => 'integer',
        'idContrato' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Relación con Acta
     */
    public function acta()
    {
        return $this->belongsTo(Acta::class, 'idActa');
    }

    /**
     * Relación con Contrato
     */
    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }
}