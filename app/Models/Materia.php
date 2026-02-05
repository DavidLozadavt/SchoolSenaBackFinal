<?php

namespace App\Models;

use App\Traits\FilterCompany;
use App\Traits\SaveFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    use HasFactory, FilterCompany, SaveFile;

    protected $table = 'materia';

  
    public $timestamps = true;

    public static $snakeAttributes = false;

    protected $fillable = [
        'nombreMateria', 
        'descripcion', 
        'idEmpresa', 
        'idAreaConocimiento', 
        'idMateriaPadre', 
        'codigo', 
        'creditos', 
        'horas'
    ];

    protected $hidden = [
        'idEmpresa' 
    ];

    protected $appends = ['DocUrl'];

    const RUTA_FILE = "materias";
    const RUTA_FOTO_DEFAULT = "/default/auto.png";


    public function getDocUrlAttribute()
    {
        if (isset($this->attributes['rutaDoc']) && !empty($this->attributes['rutaDoc'])) {
            return url($this->attributes['rutaDoc']);
        }
        return url(self::RUTA_FOTO_DEFAULT);
    }

    // --- RELACIONES ---

   /*
public function asignacionMateriaProgramas()
{
    return $this->hasMany(AsignacionMateriaPrograma::class, 'idMateria');
}
*/


    public function padre()
    {
        return $this->belongsTo(Materia::class, 'idMateriaPadre');
    }
    public function gradoMateria()
    {
        return $this->hasMany(GradoMateria::class, 'idMateria');
    }
    public function agregarMateriaPrograma()
    {
        return $this->hasMany(AgregarMateriaPrograma::class, 'idMateria');
    }
}