<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradoPrograma extends Model
{
    protected $table = 'gradoPrograma';
    
    protected $fillable = ['idPrograma', 'idGrado', 'cupos'];

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }

    public function grado()
    {
        return $this->belongsTo(Grado::class, 'idGrado');
    }

    public function materias()
    {
        return $this->hasMany(GradoMateria::class, 'idGradoPrograma');
    }
}