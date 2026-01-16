<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoEstado extends Model
{
    use HasFactory;
    protected $table = 'documento_estado';
    public static $snakeAttributes = false;

    
    public $timestamps = false;

    public function documentosPago()
    {
        return $this->belongsTo(DocumentoPago::class, 'idDocumento');
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'idPago');
    }

}
