<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AsistenciaAdministracion extends Model
{
  use HasFactory;

  protected $guarded = ['id'];

  protected $table = 'asistenciaAdministracion';

  public function contrato(): BelongsTo
  {
    return $this->belongsTo(Contract::class, 'idContrato', 'id');
  }

  public function excusas(): BelongsToMany
  {
    return $this->belongsToMany(
      Excusa::class, // Nombre de la clase del modelo relacionado
      'justificacionInasistenciaAdministracion', // Nombre de la tabla pivote
      'idAsisAdmin', // Nombre de la clave foránea en la tabla pivote
      'idExcusa' // Nombre de la clave foránea del modelo relacionado
    );
  }

}
