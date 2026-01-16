<?php

namespace App\Models;

use App\Models\Nomina\HoraExtra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObservacionSolicitudHoraExtra extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $table = "observacionSolicitudHorasExtra";




    public function horaExtra()
    {
        return $this->belongsTo(HoraExtra::class, 'idHoraExtra');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }
}
