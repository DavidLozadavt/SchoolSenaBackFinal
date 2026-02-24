<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Excusa extends Model
{
  use HasFactory;

  protected $table = 'excusa';

  protected $guarded = ['id'];

  protected $appends = ['urlDocumento'];

  public function getUrlDocumentoAttribute()
  {
    if (
      isset($this->attributes['urlDocumento']) &&
      isset($this->attributes['urlDocumento'][0])
    ) {
      return url($this->attributes['urlDocumento']);
    } else {
      return url();
    }
  }

  /**
   * Set the horaInicialJustificacion attribute.
   *
   * @param  string|null  $value
   * @return void
   */
  public function setHoraInicialJustificacionAttribute($value)
  {
    if ($value !== null) {
      $this->attributes['horaInicialJustificacion'] = $this->cleanTimeString($value);
    }
  }

  /**
   * Set the horaFinalJustificacion attribute.
   *
   * @param  string|null  $value
   * @return void
   */
  public function setHoraFinalJustificacionAttribute($value)
  {
    if ($value !== null) {
      $this->attributes['horaFinalJustificacion'] = $this->cleanTimeString($value);
    }
  }
  
  /**
   * Clean the time string by removing unnecessary characters.
   *
   * @param  string  $value
   * @return string
   */
  private function cleanTimeString($value)
  {
    // Remove backslashes and quotes
    $value = str_replace(['\\', '"'], '', $value);

    // Ensure the value is in "HH:MM:SS" format
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
      // Append ":00" if the time is in "HH:MM" format
      if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value . ':00';
      }
      return $value;
    }

    // If the value is in an unexpected format, throw an exception
    throw new \InvalidArgumentException("Invalid time format: $value");
  }


  public function asistencias(): BelongsToMany
  {
    return $this->belongsToMany(
      Asistencia::class, // Nombre de la clase del modelo relacionado
      'justificacioninasistencia', // Nombre de la tabla pivote
      'idExcusa', // Nombre de la clave foránea en la tabla pivote
      'idAsistencia' // Nombre de la clave foránea del modelo relacionado
    );
  }

  /**
   * Relation to get only justifications by this excuse
   *
   * @return HasMany
   */
  public function justificacionExcusas(): HasMany
  {
    return $this->hasMany(JustificacionInasistencia::class, 'idExcusa', 'id');
  }

  /**
   * Relation to get only justifications by this excuse including excuses by teachers
   *
   * @return HasMany
   */
  public function justificacionExcusasAdmin(): HasMany
  {
    return $this->hasMany(JustificacionInasistenciaAdministracion::class, 'idExcusa', 'id');
  }

  public function asistenciasAdministracion(): BelongsToMany
  {
    return $this->belongsToMany(
      AsistenciaAdministracion::class, // Nombre de la clase del modelo relacionado
      'justificacioninasistenciaadministracion', // Nombre de la tabla pivote
      'idExcusa', // Nombre de la clave foránea en la tabla pivote
      'idAsisAdmin' // Nombre de la clave foránea del modelo relacionado
    );
  }
}
