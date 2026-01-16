<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    use HasFactory;
    const PATH = 'perfil';

    protected $table = "persona";
    protected $fillable = [
        "identificacion",
        "nombre1",
        "nombre2",
        "apellido1",
        "apellido2",
        "fechaNac",
        "idCiudadNac",
        "idCiudad",
        "direccion",
        "email",
        "idTipoIdentificacion",
        "telefonoFijo",
        "celular",
        "idCiudadUbicacion",
        "perfil",
        "sexo",
        "rh",
    ];
    const RUTA_FOTO = "persona";
    const RUTA_FOTO_DEFAULT = "/default/user.svg";

    protected $appends = ['rutaFotoUrl'];

    public function getRutaFotoUrlAttribute()
    {
        if (
            isset($this->attributes['rutaFoto']) &&
            isset($this->attributes['rutaFoto'][0])
        ) {
            return url($this->attributes['rutaFoto']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }

    public function usuario()
    {
        return $this->hasOne(User::class, 'idpersona');
    }

    public function ubicacion()
    {
        return $this->belongsTo(City::class, 'idCiudadUbicacion');
    }

    public function ciudad()
    {
        return $this->belongsTo(City::class, 'idCiudad');
    }

    public function ciudadNac()
    {
        return $this->belongsTo(City::class, 'idCiudadNac');
    }

    public function ciudadUbicacion()
    {
        return $this->belongsTo(City::class, 'idCiudadUbicacion');
    }

    public function tipoIdentificacion()
    {
        return $this->belongsTo(IdentificationType::class, 'idTipoIdentificacion');
    }

    public function contrato()
    {
        return $this->hasMany(Contract::class, 'idpersona');
    }

    public function asignacionesConductor()
    {
        return $this->hasMany(AsignacionConductor::class, 'idConductor');
    }


    public function contratoActivo()
    {
        return $this->hasOne(Contract::class, 'idpersona')
            ->where('idEstado', 1)
            ->latest('fechaContratacion');
    }


    public function observacionesPreocupacionales()
    {
        return $this->hasMany(ObservacionPreocupacional::class, 'idPersona');
    }


    public function restricciones()
    {
        return $this->hasMany(Restriccion::class, 'idPersona');
    }


    public function personaNatural()
    {
        return $this->hasOne(InformacionPersonaNatural::class, 'idPersona', 'id');
    }

      public function asignacionPropietario()
    {
        return $this->hasOne(AsignacionPropietario::class, 'idPropietario', 'id');
    }


    public function referenciasPersonales()
    {
        return $this->hasMany(ReferenciaPersonal::class, 'idPersona');
    }
}
