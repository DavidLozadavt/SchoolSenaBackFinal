<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustificacionInasistencia extends Model
{
  use HasFactory;

  protected $table = 'justificacionInasistencia';

  protected $guarded = ['id'];

  public function asistencia(): BelongsTo
  {
    return $this->belongsTo(Asistencia::class, 'idAsistencia', 'id');
  }

  public function excusa(): BelongsTo
  {
    return $this->belongsTo(Excusa::class, 'idExcusa', 'id');
  }

  public function matriculaAcademica(): BelongsTo
  {
    return $this->belongsTo(MatriculaAcademica::class, 'idMatriculaAcademica', 'id');
  }

  public function persona(): BelongsTo
  {
    return $this->belongsTo(Person::class, 'idPersona', 'id');
  } 

}
