<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionAreaConocimientoPrograma extends Model
{
    use HasFactory;

    protected $table = 'asignacionAreaConocimientoPrograma';
    protected $primaryKey = 'id';

    protected $fillable = [
        'idAreaConocimiento',
        'idPrograma',
    ];

    public function areaConocimiento()
    {
        return $this->belongsTo(AreaConocimiento::class, 'idAreaConocimiento');
    }

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }
}
