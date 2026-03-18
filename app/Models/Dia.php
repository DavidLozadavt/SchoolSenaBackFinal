<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dia extends Model
{
  use HasFactory;

  protected $table = 'dia';

  protected $guarded = ['id'];

  public $timestamps = false;

/*   public function jornadas(): BelongsToMany
  {
    return $this->belongsToMany(Jornada::class, 'asignacionDiaJornada', 'idDia', 'idJornada');
  } */

  public function horariosMateria(): HasMany
  {
    return $this->hasMany(HorarioMateria::class, 'idDia');
  }
}
