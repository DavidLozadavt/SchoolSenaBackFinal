<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipanteEvento extends Model
{
    use HasFactory;

    protected $table = 'participante_evento';
    
    // Si la tabla no tiene id incremental (es una tabla pivote con datos extra)
    public $incrementing = false;
    protected $primaryKey = ['idPersona', 'idEvento'];

    protected $guarded = [];

    /**
     * Relación con la persona/usuario
     */
    public function persona()
    {
        return $this->belongsTo(Person::class, 'idPersona');
    }

    /**
     * Relación con el evento
     */
    public function evento()
    {
        return $this->belongsTo(Evento::class, 'idEvento');
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQueryVal($keyName));
        }

        return $query;
    }

    /**
     * Get the value of the model's primary key for a save query.
     */
    protected function getKeyForSaveQueryVal($keyName)
    {
        return $this->original[$keyName] ?? $this->getAttribute($keyName);
    }
}

