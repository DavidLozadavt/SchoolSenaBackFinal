<?php

namespace App\Models;

use App\Models\Nomina\ConfiguracionNomina;
use App\Models\Nomina\HistorialConfiguracionNomina;
use App\Models\Nomina\Sede;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    protected $table = "empresa";

    protected $fillable = [
        'razonSocial',
        'nit',
        'representanteLegal',
        'direccion',
        'email',
        'rutaLogo',
        'digitoVerificacion',
    ];


    const RUTA_LOGO_DEFAULT = "/default/logoweb.png";
    const RUTA_LOGO = "company";
    const VIRTUALT = 1;

    protected $appends = ['rutaLogoUrl'];

    public function getRutaLogoUrlAttribute()
    {
        if (
            isset($this->attributes['rutaLogo']) &&
            isset($this->attributes['rutaLogo'][0])
        ) {
            return url($this->attributes['rutaLogo']);
        }
        return url(self::RUTA_LOGO_DEFAULT);
    }

    public function empresa()
    {
        return $this->hasOne(Contract::class, 'idempresa');
    }

    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class, 'idEmpresa');
    }

    public function configuracionNomina(): HasOne
    {
        return $this->hasOne(ConfiguracionNomina::class, 'idEmpresa')->latest();
    }

    public function historialConfiguracionesNomina(): HasMany
    {
        return $this->hasMany(HistorialConfiguracionNomina::class, 'idEmpresa');
    }
    public function ciudad()
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }
}
