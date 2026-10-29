<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matricula extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'matricula';

    protected $casts = [
        'fecha' => 'date',
        'condicionado' => 'boolean',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'idFicha');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class, 'idGrado');
    }

    public function acudiente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idAcudiente');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }
    public function anotacionesDisciplinarias()
    {
        return $this->hasMany(AnotacionesDisciplinarias::class, 'idEstudiante', 'id');
    }
}