<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoAfiliacion extends Model
{
    use HasFactory;

    protected $table = "tipoAfiliacion";

    protected $fillable = ['tipoAfiliacion', 'observacion'];

    public $timestamps = false;
}
