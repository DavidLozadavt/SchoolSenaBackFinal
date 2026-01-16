<?php

namespace App\Models\Nomina;

use App\Models\Company;
use App\Models\PuntoVenta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AsignacionPeriodoPrograma;
class Sede extends Model
{
    use HasFactory;

    protected $fillable = [
        "nombre",
        "direccion",
        "email",
        "telefono",
        "celular",
        "urlSede",
        "descripcion",
        "idResponsable",
        "idEmpresa"
    ];
    protected $table = 'sedes';

    const RUTA_FOTO_DEFAULT = "default/sede.png";

    protected $appends = ['rutaImagenUrl'];

    public function getRutaImagenUrlAttribute()
    {
        $defaultUrl = url('storage/' . self::RUTA_FOTO_DEFAULT);

        if (isset($this->attributes['urlImagen'])) {
            if ($this->attributes['urlImagen'] === self::RUTA_FOTO_DEFAULT) {
                return url($this->attributes['urlImagen']);
            }

            return url('storage/' . $this->attributes['urlImagen']);
        }

        return $defaultUrl;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'idSede');
    }
    public function responsable()
    {
        return $this->belongsTo(User::class, 'idResponsable');
    }


    public function puntosVenta()
    {
        return $this->hasMany(PuntoVenta::class, 'idSede');

    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionPeriodoPrograma::class, 'idSede');
    }
}
