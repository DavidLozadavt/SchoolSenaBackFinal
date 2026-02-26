<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClasificacionActividad extends Model
{
    protected $table = 'clasificacionActividad';

    protected $fillable = ['nombreClasificacionActividad', 'porcentaje', 'idCompany', 'idPrograma'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }
}
