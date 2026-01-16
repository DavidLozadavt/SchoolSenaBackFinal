<?php

namespace App\Models\Nomina;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoraExtra extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'horaExtras';

    const RUTA_FOTO_DEFAULT = "default/sede.png";

    protected $appends = ['rutaEvidenciaUrl'];

    public function getRutaEvidenciaUrlAttribute()
    {
        $defaultUrl = url('storage/' . self::RUTA_FOTO_DEFAULT);

        if (isset($this->attributes['urlEvidencia'])) {
            if ($this->attributes['urlEvidencia'] === self::RUTA_FOTO_DEFAULT) {
                return url($this->attributes['urlEvidencia']);
            }

            return url('storage/' . $this->attributes['urlEvidencia']);
        }

        return $defaultUrl;
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'idContrato');
    }


    public function configuracionHoraExtra(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionHoraExtra::class, 'idConfiguracionHorasExtra');
    }
}
