<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MultimediaHistorias extends Model
{
    use HasFactory;

    protected $table = "multimedia_historias";
    const RUTA_DEFAULT = "/default/logoweb.png";
    const RUTA_MULTIMEDIA = "multimedia-historias";

    protected $fillable = [
        'idGrupoMultimedia',
        'idCompany',
        'idUser',
        'urlMultimedia',
        'cancion',
        'descripcion',
        'tipo',
    ];

    protected $casts = [
        'idUser' => 'int',
    ];

    protected $appends = ['urlMultimediaFull'];

    public function getUrlMultimediaFullAttribute()
    {
        $raw = $this->attributes['urlMultimedia'] ?? null;
        if (!$raw) return url(self::RUTA_DEFAULT);
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $raw;
        }
        return url($raw);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }

    public function grupoMultimedia()
    {
        return $this->belongsTo(GrupoMultimedia::class, 'idGrupoMultimedia');
    }
}
