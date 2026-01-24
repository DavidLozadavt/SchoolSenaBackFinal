<?php

namespace App\Models\Nomina;

use App\Models\Company;
use App\Models\City;
use App\Models\User;
use App\Models\PuntoVenta;
use App\Models\nomina\Area;
use App\Models\AsignacionPeriodoPrograma;
use App\Models\Infraestructura;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';
    protected $primaryKey = 'id';

    const RUTA_FOTO_DEFAULT = "default/sede.png";

    protected $fillable = [
        "nombre",
        "direccion",
        "email",
        "telefono",
        "celular",
        "urlImagen",
        "descripcion",
        "idResponsable",
        "idEmpresa",
        "idCiudad",
        "tipo"
    ];

    protected $appends = ['rutaImagenUrl'];

    /* =========================
       📸 Imagen
    ========================= */
    public function getRutaImagenUrlAttribute()
    {
        $defaultUrl = url('storage/' . self::RUTA_FOTO_DEFAULT);

        if (!empty($this->attributes['urlImagen'])) {
            if ($this->attributes['urlImagen'] === self::RUTA_FOTO_DEFAULT) {
                return url($this->attributes['urlImagen']);
            }

            return url('storage/' . $this->attributes['urlImagen']);
        }

        return $defaultUrl;
    }

    /* =========================
       🔗 Relaciones
    ========================= */
    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idEmpresa');
    }

    public function ciudad()
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'idResponsable');
    }

    public function areas()
    {
        return $this->hasMany(Area::class, 'idSede');
    }

    public function puntosVenta()
    {
        return $this->hasMany(PuntoVenta::class, 'idSede');
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionPeriodoPrograma::class, 'idSede');
    }

    public function infraestructuras()
    {
        return $this->hasMany(Infraestructura::class, 'idSede');
    }
}
