<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponseCommentCheckItem extends Model
{
    use HasFactory;

    protected $table = "responseCommentCheckItem";
    

    public function parent()
    {
        return $this->hasMany(ResponseCommentCheckItem::class, 'idResponseComment');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }



    const RESPONSE_DEFAULT = "/default/user.svg";
  

    protected $appends = ['rutaUrlItem'];

    public function getRutaUrlItemAttribute()
    {
        if (
            isset($this->attributes['urlArchivo']) &&
            isset($this->attributes['urlArchivo'][0])
        ) {
            return url($this->attributes['urlArchivo']);
        }
        return url(self::RESPONSE_DEFAULT);
    }
}
