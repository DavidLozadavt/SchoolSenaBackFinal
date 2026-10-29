<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Llaves de la pasarela de pagos (Wompi) de una institución.
 * Los secretos se cifran con APP_KEY y nunca se devuelven completos al navegador.
 */
class EmpresaPasarelaPago extends Model
{
    protected $table = 'empresaPasarelaPago';
    protected $guarded = ['id'];

    protected $hidden = ['privateKey', 'integritySecret', 'eventsSecret'];

    protected $casts = [
        'privateKey' => 'encrypted',
        'integritySecret' => 'encrypted',
        'eventsSecret' => 'encrypted',
        'habilitarPSE' => 'boolean',
        'habilitarTarjetas' => 'boolean',
        'activo' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }

    public function completa(): bool
    {
        return !empty($this->publicKey)
            && !empty($this->privateKey)
            && !empty($this->integritySecret)
            && !empty($this->eventsSecret);
    }
}
