<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttachmentsCard extends Model
{
    use HasFactory;

    protected $table = "attachmentsCard"; 


    const ATTACHMENT_DEFAULT = "/default/user.svg";
  

    protected $appends = ['rutaUrl'];

    public function getRutaUrlAttribute()
    {
        if (
            isset($this->attributes['urlArchivo']) &&
            isset($this->attributes['urlArchivo'][0])
        ) {
            return url($this->attributes['urlArchivo']);
        }
        return url(self::ATTACHMENT_DEFAULT);
    }
}
