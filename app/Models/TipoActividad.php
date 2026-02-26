<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoActividad extends Model
{
    protected $table = 'tipoActividades';

    protected $fillable = ['tipoActividad', 'descripcion', 'idCompany'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }
}
