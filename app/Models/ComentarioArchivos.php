<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComentarioArchivos extends Model
{
	use HasFactory;

	protected $guarded = ['id'];

	protected $table = 'comentarioArchivos';

	protected $appends = ['archivo'];

	public function getArchivoAttribute()
	{
		if (
			isset($this->attributes['archivo']) &&
			isset($this->attributes['archivo'][0])
		) {
			return url($this->attributes['archivo']);
		}
	}

	public function comentario(): BelongsTo
	{
		return $this->belongsTo(Comentario::class, 'idComentario');
	}
}
