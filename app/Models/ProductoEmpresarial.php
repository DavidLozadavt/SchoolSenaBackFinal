<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoEmpresarial extends Model
{
    use HasFactory;

    protected $table = "productoEmpresarial";

    protected $fillable = [
        "nombreProducto",
        'descripcionProducto',
        'version',
        'idEstado',
        'fechaCreacion',
        'idTipoProductoEmpresarial'
    ];
}
