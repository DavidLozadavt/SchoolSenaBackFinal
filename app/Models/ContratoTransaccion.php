<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratoTransaccion extends Model
{
    use HasFactory;

    protected $table = 'asignacion_contrato_transaccion';
    public $timestamps = false;
    public static $snakeAttributes = false;


    protected $fillable = [
        'transaccion_id'
    ];

    public function transaccion()
    {
        return $this->belongsTo(Transaccion::class, 'transaccion_id');
    }

}
