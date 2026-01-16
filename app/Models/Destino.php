<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Destino extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $cast = [
        'principal' => 'boolean',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

        public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'idtercero');
    }

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }
}
