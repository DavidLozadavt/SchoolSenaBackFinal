<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comentario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comentarios';

    protected $guarded = ['id'];

    public function activationCompanyUser(): BelongsTo
    {
        return $this->belongsTo(ActivationCompanyUser::class, 'idActivationCompanyUser');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionComentarios::class, 'idComentario');
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(GrupoChat::class, 'asignacionComentarios', 'idComentario', 'idGrupo');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(ComentarioArchivos::class, 'idComentario', 'id');
    }
}
