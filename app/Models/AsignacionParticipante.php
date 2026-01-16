<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionParticipante extends Model
{
  use HasFactory;

  protected $guarded = ['id'];

  protected $table = 'asignacionParticipantes';

  public function grupo(): BelongsTo
  {
    return $this->belongsTo(GrupoChat::class, 'idGrupo');
  }

  public function activationUser(): BelongsTo
  {
    return $this->belongsTo(ActivationCompanyUser::class, 'idActivationUser');
  }

}
