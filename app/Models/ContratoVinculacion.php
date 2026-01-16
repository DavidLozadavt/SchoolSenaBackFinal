<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratoVinculacion extends Model
{
  use HasFactory;

  protected $table = "contratoVinculacion";

  const RUTA_FILE = "/default/user.svg";


  protected $appends = ['urlRutaArchivo'];

  public function getUrlRutaArchivoAttribute()
  {
    if (
      isset($this->attributes['urlArchivo']) &&
      isset($this->attributes['urlArchivo'][0])
    ) {
      return url($this->attributes['urlArchivo']);
    }
    return url(self::RUTA_FILE);
  }
}
