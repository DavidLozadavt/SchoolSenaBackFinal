<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionConductor extends Model
{
    use HasFactory;

    protected $table = "asignacionConductor";

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idConductor');
    }



}