<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservacionSolicitudVacacion extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'observacionSolicitudVacaciones';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudVacacion::class, 'idSolicitud');
    }

}
