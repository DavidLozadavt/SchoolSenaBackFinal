<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Formulario extends Model
{
    use HasFactory;

    protected $table = 'formularios';

    protected $guarded = ['id'];

    protected $casts = [
        'requiereAutenticacion' => 'boolean',
        'fechaLimite' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($formulario) {
            if (empty($formulario->slug)) {
                $formulario->slug = Str::slug($formulario->titulo) . '-' . uniqid();
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function preguntas()
    {
        return $this->hasMany(FormularioPregunta::class, 'idFormulario')->orderBy('orden');
    }

    public function respuestas()
    {
        return $this->hasMany(FormularioRespuesta::class, 'idFormulario');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'publicado');
    }
}
