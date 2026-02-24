<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustificacionInasistenciaAdministracion extends Model
{
  use HasFactory;

  protected $guarded = ['id'];

  protected $table = 'justificacionInasistenciaAdministracion';

  public function asistenciaAdmin(): BelongsTo
  {
    return $this->belongsTo(AsistenciaAdministracion::class, 'idAsisAdmin');
  }

  public function excusa(): BelongsTo
  {
    return $this->belongsTo(Excusa::class, 'idExcusa', 'id');
  }

  public function contrato(): BelongsTo
  {
    return $this->belongsTo(Contract::class, 'idContrato', 'id');
  }

  public function persona(): BelongsTo
  {
    return $this->belongsTo(Person::class, 'idPersona', 'id');
  }
}
