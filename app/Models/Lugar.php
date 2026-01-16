<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lugar extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'lugares';

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }

}
