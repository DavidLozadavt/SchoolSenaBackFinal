<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AsignacionComentarios extends Model
{
	use HasFactory;

	protected $guarded = ['id'];

	protected $table = 'asignacionComentarios';

	public function grupo(): BelongsTo
	{
		return $this->belongsTo(GrupoChat::class, 'idGrupo');
	}

	public function comentario(): BelongsTo
	{
		return $this->belongsTo(Comentario::class, 'idComentario');
	}

}
