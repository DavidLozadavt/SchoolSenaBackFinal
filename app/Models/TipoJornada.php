<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoJornada extends Model
{
    use HasFactory;

    protected $table = 'tipojornada';

    public const MATERIAS = 4;
    public const EVENTOS = 3;
    public const PROGRAMAS_FORMACION = 2;
    public const PROGRAMACION_CHAT = 1;

    protected $fillable = [
        'nombreTipoJornada',
        'descripcion',
    ];


    /**
     * Relación:
     * Un tipo de jornada tiene muchas jornadas
     */
    public function jornadas()
    {
        return $this->hasMany(Jornada::class, 'idTipoJornada');
    }
}
