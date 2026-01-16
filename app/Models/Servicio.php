<?php

namespace App\Models;

use App\Traits\SaveFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Servicio extends Model
{
    use HasFactory, SaveFile;


    const RUTA_SERVICIO = "servicio";
    const RUTA_SERVICIO_DEFAULT = "/default/user.svg";
    const PATH = 'servicio';

    protected $appends = ['rutaServicioUrl'];

    public static $snakeAttributes = false;
    protected $guarded = [];
    protected $table = "servicios";



    public function saveImageServicio($request)
    {
        $default = '/default/servicio.png';
        if (isset($this->attributes['urlImage'])) {
            $default = $this->attributes['urlImage'];
        }
        $this->attributes['urlImage'] = $this->storeFile(
            $request,
            'urlImage',
            self::PATH,
            $default
        );
        return $this->attributes['urlImage'];
    }




    public function getrutaServicioUrlAttribute()
    {
        if (
            isset($this->attributes['urlImage']) &&
            isset($this->attributes['urlImage'][0])
        ) {
            return url($this->attributes['urlImage']);
        }
        return url(
            self::RUTA_SERVICIO_DEFAULT
        );
    }


    public function tipoServicio()
    {
        return $this->belongsTo(TipoServicio::class, 'idTipoServicio');
    }

    public function categoriaServicio(): BelongsTo
    {
        return $this->belongsTo(CategoriaServicio::class, 'idCategoriaServicio');
    }

    public function escenarios()
    {
        return $this->belongsToMany(
            Escenario::class,
            'asignacionEscenarioServicio',
            'idServicio',
            'idEscenario'
        );
    }

    public function responsables()
    {
        return $this->hasManyThrough(
            ResponsableServicio::class,
            PrestacionServicio::class,
            'idArticuloServicio',     // FK en prestacionServicios → referencia a articulosServicio
            'id',                     // PK de ResponsableServicio
            'id',                     // PK de Servicio
            'idResponsable'           // FK en prestacionServicios → referencia a responsable
        )->withPivot('estado');
    }
}
