<?php

namespace App\Models\Nomina;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroCosto extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'centroCostos';

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'idSede');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'idArea');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contract::class, 'idCentroCosto');
    }

}
