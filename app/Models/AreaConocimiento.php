<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaConocimiento extends Model
{
    use HasFactory;

    protected $table = 'area_conocimiento';

    protected $fillable = [
        'nombreAreaConocimiento'
    ];

    public function niveleEducativo () {
        return $this->belongsTo(NivelEducativo::class, 'idNivelEducativo');
    }

    public function materias () {
        return $this->hasMany(Materia::class, 'idAreaConocimiento', 'id');
    }
}
