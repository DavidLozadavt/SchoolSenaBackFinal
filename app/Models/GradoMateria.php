<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradoMateria extends Model
{
    protected $table = 'gradoMateria';
    
    protected $fillable = [
        'rutaDocumento', 
        'idGradoPrograma', 
        'idMateria', 
        'idDocente',
        'estado'
    ];

    public function gradoPrograma()
    {
        return $this->belongsTo(GradoPrograma::class, 'idGradoPrograma');
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

    public function docente(){
        return $this->belongsTo(ActivationCompanyUser::class, 'idDocente');
    }

    public function horarioMateria(){
        return $this->hasMany(HorarioMateria::class, 'idGradoMateria');
    }
}