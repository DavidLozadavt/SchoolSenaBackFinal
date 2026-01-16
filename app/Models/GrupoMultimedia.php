<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrupoMultimedia extends Model
{
    use HasFactory;


    protected $table = "grupoMultimedia";

    public function gruposMultimedia()
    {
        return $this->hasMany(MultimediaHistorias::class, 'idGrupoMultimedia');
    }


    public function empresa()
    {
        return $this->belongsTo(Company::class, 'idCompany');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }




}
