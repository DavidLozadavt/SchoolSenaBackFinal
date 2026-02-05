<?php

namespace App\Models;

use App\Models\Materia;
use App\Models\Programa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgregarMateriaPrograma extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'agregarMateriaPrograma';

    public $timestamps = false;

    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'idPrograma');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'idMateria');
    }

}
