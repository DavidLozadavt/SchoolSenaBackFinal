<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Nomina\Area;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'evento';
    protected $primaryKey = 'idEvento';

    protected $guarded = [];

    /**
     * Relación con el área/sede del evento
     */
    public function area()
    {
        return $this->belongsTo(Area::class, 'idArea');
    }

    /**
     * Relación con la configuración de repetición (si aplica)
     */
    public function configuracionRepeat()
    {
        return $this->belongsTo(ConfiguracionRepeatCard::class, 'idConfiguracionRepeatCard');
    }

    /**
     * Relación con los participantes
     */
    public function participantes()
    {
        return $this->hasMany(ParticipanteEvento::class, 'idEvento');
    }

    /**
     * Relación con el grupo multimedia (historia vinculada)
     */
    public function grupoMultimedia()
    {
        return $this->belongsTo(GrupoMultimedia::class, 'idGrupoMultimedia');
    }
}
