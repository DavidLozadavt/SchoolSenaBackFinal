<?php

namespace App\Models;

use App\Traits\FilterCompany;
use App\Traits\SaveFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'horas',
        'idCompany',
        'idCategoriaFormacion'
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

    public function areaConocimiento()
    {
        return $this->belongsTo(AreaConocimiento::class, 'idAreaConocimiento');
    }

    public function categoriaFormacion()
    {
        return $this->belongsTo(CategoriaFormacion::class, 'idCategoriaFormacion');
    }
    public function hijas()
    {
        return $this->hasMany(Materia::class, 'idMateriaPadre');
    }

    /** True si es un RAP (hijo de una competencia). False si es competencia u otro nodo raíz. */
    public function esRap(): bool
    {
        return ! empty($this->idMateriaPadre) && (int) $this->idMateriaPadre > 0;
    }

    /** True si es competencia (sin padre). */
    public function esCompetencia(): bool
    {
        return empty($this->idMateriaPadre) || (int) $this->idMateriaPadre <= 0;
    }

    /**
     * Número de orden del RAP (01, 02, …) a partir de código o nombre.
     * No usa el id de BD.
     */
    public static function numeroOrdenRap(?string $codigo, ?string $nombre = null): int
    {
        $codigo = trim((string) $codigo);
        $nombre = trim((string) $nombre);

        foreach ([$codigo, $nombre] as $texto) {
            if ($texto === '') {
                continue;
            }
            // "RAP 01", "RAP-02", "R.A.P. 3"
            if (preg_match('/\br\.?\s*a\.?\s*p\.?\s*[-#:]?\s*0*(\d{1,3})\b/iu', $texto, $m)) {
                return (int) $m[1];
            }
            // Código corto numérico: "01", "2", "04"
            if (preg_match('/^0*(\d{1,3})$/', $texto, $m)) {
                return (int) $m[1];
            }
            // "593101 - 02 ESTRUCTURAR..." o "... - 02 - ..."
            if (preg_match('/(?:^|[\s\-–—])0*(\d{1,3})(?:\s*[\-–—]\s*|\s+)/u', $texto, $m)) {
                $n = (int) $m[1];
                // Evitar tomar códigos largos tipo 593101 como número de RAP
                if ($n > 0 && $n <= 99) {
                    return $n;
                }
            }
            // Al inicio del nombre: "02 ESTRUCTURAR..."
            if (preg_match('/^0*(\d{1,2})\b/', $texto, $m)) {
                return (int) $m[1];
            }
        }

        return PHP_INT_MAX;
    }

    public function faseProyectoRaps(): HasMany
    {
        return $this->hasMany(FaseProyectoRap::class, 'idMateria');
    }
    public function faseProyectoMaterias(): HasMany
    {
        return $this->hasMany(FaseProyectoMateria::class, 'idMateria');
    }
}