<?php

namespace App\Models;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoServicio extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'catalogoServicios';

    protected $appends = ['url'];

    public function getUrlAttribute(): string|UrlGenerator
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
