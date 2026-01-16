<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoReemplazo extends Model
{
  use HasFactory;

  protected $table = 'pagosReemplazo';

  public function reemplazo()
  {
    return $this->belongsTo(Reemplazo::class, 'idReemplazo');
  }
}
