<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionMantenimiento extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'configuracionMantenimiento';

     /**
     * Relación con Empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }

}
