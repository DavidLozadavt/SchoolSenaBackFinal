<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoIncapacidad extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'tipoIncapacidades';

    public function solicitudIncapacidades(): HasMany
    {
        return $this->hasMany(SolicitudIncLicPersona::class, 'idTipoSolicitud');
    }

}
