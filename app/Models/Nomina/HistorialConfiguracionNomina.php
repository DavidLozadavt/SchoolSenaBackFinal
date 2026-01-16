<?php

namespace App\Models\Nomina;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialConfiguracionNomina extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'historialConfiguracionNominas';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }
}
