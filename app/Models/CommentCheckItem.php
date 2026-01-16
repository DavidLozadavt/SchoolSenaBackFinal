<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentCheckItem extends Model
{
    use HasFactory;
    protected $table = "commentCheckItem";
    

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    const COMMENT_DEFAULT = "/default/user.svg";
  

    protected $appends = ['rutaUrlItem'];

    public function getRutaUrlItemAttribute()
    {
        if (
            isset($this->attributes['urlArchivo']) &&
            isset($this->attributes['urlArchivo'][0])
        ) {
            return url($this->attributes['urlArchivo']);
        }
        return url(self::COMMENT_DEFAULT);
    }


    public function responses()
    {
        return $this->hasMany(ResponseCommentCheckItem::class, 'idCommentCheckItem');
    }

    
   
}
