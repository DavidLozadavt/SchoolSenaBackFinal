<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageBoard extends Model
{
    use HasFactory;

    protected $table = 'imageBoard';


    const RUTA_BOARD_DEFAULT = "/default/user.svg";
  

    protected $appends = ['rutaBoardUrl'];

    public function getRutaBoardUrlAttribute()
    {
        if (
            isset($this->attributes['urlFile']) &&
            isset($this->attributes['urlFile'][0])
        ) {
            return url($this->attributes['urlFile']);
        }
        return url(self::RUTA_BOARD_DEFAULT);
    }
}
