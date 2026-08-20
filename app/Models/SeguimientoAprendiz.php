<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoAprendiz extends Model
{
    use HasFactory;

    protected $table = 'seguimientoAprendiz';

    protected $fillable = [
        'idpersona',
        'idcontrato',
        'estado',
    ];

    // Estados válidos para validación reutilizable
    public const ESTADOS = [
        'PENDIENTE',
        'EN_PROCESO',
        'ATRASADO',
        'SUSPENDIDO',
        'FINALIZADO',
        'CANCELADO',
    ];

    public function persona()
    {
        return $this->belongsTo(Person::class, 'idpersona');
    }

    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idcontrato');
    }

    public function documentos()
    {
        return $this->hasMany(DocumentoSeguimiento::class, 'idseguimiento');
    }
}