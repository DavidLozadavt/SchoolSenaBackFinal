<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MultimediaHistorias extends Model
{
    use HasFactory;

    protected $table = "multimediaHistorias";
    const RUTA_DEFAULT = "/default/logoweb.png";
    const RUTA_MULTIMEDIA = "multimedia-historias";

    protected $casts = [
        'idUser' => 'int',
    ];


    protected $appends = ['urlMultimedia'];


    public function getUrlMultimediaAttribute()
    {
        if (
            isset($this->attributes['urlMultimedia']) &&
            !empty($this->attributes['urlMultimedia'])
        ) {
            return url($this->attributes['urlMultimedia']);
        }
        return url(self::RUTA_DEFAULT);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }
    public function gruposMultimedia()
    {
        return $this->hasMany(MultimediaHistorias::class, 'idGrupoMultimedia');
    }


}
