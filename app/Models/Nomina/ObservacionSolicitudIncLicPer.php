<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObservacionSolicitudIncLicPer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'observacionSolicitudIncLicPers';

    public function solicitudIncLicPer(): BelongsTo
    {
        return $this->belongsTo(SolicitudIncLicPersona::class, 'idSolicitud');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class,'idUsuario');
    }

}
