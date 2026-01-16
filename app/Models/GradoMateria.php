<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradoMateria extends Model
{
    protected $table = 'gradoMateria';
    
    protected $fillable = [
        'rutaDocumento', 
        'idGradoPrograma', 
        'idMateria', 
        'idDocente'
    ];

    public function gradoPrograma()
    {
        return $this->belongsTo(GradoPrograma::class, 'idGradoPrograma');
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }
}