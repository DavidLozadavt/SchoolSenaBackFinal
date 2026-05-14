<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipanteEvento extends Model
{
    use HasFactory;

    protected $table = 'participante_evento';
    
    // Si la tabla no tiene id incremental (es una tabla pivote con datos extra)
    public $incrementing = false;
    protected $primaryKey = ['idPersona', 'idEvento'];

    protected $guarded = [];

    /**
     * Relación con la persona/usuario
     */
    public function persona()
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

    /**
     * Relación con el evento
     */
    public function evento()
    {
        return $this->belongsTo(Evento::class, 'idEvento');
    }
}
