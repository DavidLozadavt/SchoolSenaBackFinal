<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Corte extends Model
{
    protected $table = 'cortes';
    protected $fillable = ['numero', 'fechaInicial', 'fechaFinal', 'porcentaje', 'idApertura'];


    public $timestamps = false; // <-- Desactiva created_at y updated_at
    public function apertura()
    {
        return $this->belongsTo(AperturarPrograma::class, 'idApertura');
    }
}
