<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCuentaPropia extends Model
{
    use HasFactory;

    protected $table = "subcuentaPropia";
    public static $snakeAttributes = false;
    public $timestamps = false;

    const BANCOS_NACIONALES = 2;
    const PROVEEDORES_NACIONALES = 4;
    const CLIENTES_NACIONALES = 12;
    const DESAROLLO_DE_SOFTWARE_A_LA_MEDIDA = 13;
    const RETENCION_10_PORCIENTO = 14;
    const IVA = 8;
    const CAJA = 5;
    const INGRESOS = 6;


    public function subCuenta()
    {
        return $this->belongsTo(SubCuenta::class, 'subcuenta_id');
    }
}
