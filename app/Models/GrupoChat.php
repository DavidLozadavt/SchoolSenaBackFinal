<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrupoChat extends Model
{
  use HasFactory, SoftDeletes;

  protected $guarded = ['id'];

  protected $table = 'gruposChat';

  public function asignaciones(): HasMany
  {
    return $this->hasMany(AsignacionComentarios::class, 'idGrupo');
  }

  public function comentarios(): BelongsToMany
  {
    return $this->belongsToMany(Comentario::class, 'asignacionComentarios', 'idGrupo', 'idComentario');
  }

  public function participantes()
  {
    return $this->hasMany(AsignacionParticipante::class, 'idGrupo');
  }
}
