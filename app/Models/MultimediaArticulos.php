<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MultimediaArticulos extends Model
{
    use HasFactory;

    protected $table = "multimediaArticulos";
    protected $appends = ['url'];
    protected $fillable = [
        "idArticuloServicio",
        "observacion",
        "url"
    ];


    public function articuloServicio()
    {
        return $this->belongsTo(ArticuloServicio::class, 'idArticuloServicio');
    }


    public function getUrlAttribute()
    {
        if (
            isset($this->attributes['url']) &&
            isset($this->attributes['url'][0])
        ) {
            return url($this->attributes['url']);
        }
        return "";
    }
}
