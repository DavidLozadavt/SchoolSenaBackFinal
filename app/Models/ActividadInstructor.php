<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ActividadInstructor extends Model
{
    use HasFactory;

    protected $table = 'actividadesInstructores';

    protected $fillable = [
        'descripcion',
        'fechaInicial',
        'fechaFinal',
        'documento',
        'numeroHoras',
        'idRmi',
        'idContrato'
    ];

    const RUTA_DOCUMENTO = "instructores/actividades";

    protected $appends = ['rutaDocumentoUrl'];

    public function getRutaDocumentoUrlAttribute()
    {
        if (!empty($this->attributes['documento'])) {
            return url('storage/' . $this->attributes['documento']);
        }
        return null;
    }


    protected $casts = [
        'fechaInicial' => 'date',
        'fechaFinal' => 'date',
    ];

    public function rmi()
    {
        return $this->belongsTo(Rmi::class, 'idRmi');
    }
    public function contrato()
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }
}
