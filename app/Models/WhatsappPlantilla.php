<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappPlantilla extends Model
{
    protected $table = 'whatsappPlantillas';

    protected $fillable = [
        'nombre',
        'mensaje'
    ];

    /**
     * Disable snake-case attributes by default.
     *
     * @var bool
     */
    public static $snakeAttributes = false;
}
