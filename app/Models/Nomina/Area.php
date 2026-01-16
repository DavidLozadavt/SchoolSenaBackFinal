<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'idSede');
    }

}
