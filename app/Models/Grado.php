<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grado extends Model
{
    protected $table = 'grado';

    protected $fillable = ['numeroGrado', 'nombreGrado', 'idTipoGrado'];

    public function tipoGrado()
    {
        return $this->belongsTo(TipoGrado::class, 'idTipoGrado');
    }
}