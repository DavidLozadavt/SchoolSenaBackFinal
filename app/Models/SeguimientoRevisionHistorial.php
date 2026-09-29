<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoRevisionHistorial extends Model
{
    use HasFactory;

    protected $table = 'seguimiento_revision_historial';

    protected $guarded = ['id'];

    public function aspirante()
    {
        return $this->belongsTo(SeguimientoAspirante::class, 'idAspirante');
    }

    public function usuarioRevisor()
    {
        return $this->belongsTo(User::class, 'idUsuarioRevisor');
    }
}
