<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SesionMateria extends Model
{
  use HasFactory;

  protected $table = 'sesionMateria';

  protected $guarded = ['id'];

  public function setCreatedAtAttribute($value)
  {
    date_default_timezone_set("America/Bogota");
    $this->attributes['created_at'] = Carbon::now();
  }

  public function setUpdatedAtAttribute($value)
  {
    date_default_timezone_set("America/Bogota");
    $this->attributes['updated_at'] = Carbon::now();
  }

  public function horarioMateria(): BelongsTo
  {
    return $this->belongsTo(HorarioMateria::class, 'idHorarioMateria', 'id');
  }
}
