<?php

namespace App\Models;

use App\Util\KeyUtil;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $table = "producto";
    
    protected $fillable = [
    'cantidad',
    'valorCompra',
    'valorVenta',
    ];

    public static $snakeAttributes = false;

    const RUTA_PRODUCTO_DEFAULT = "/default/user.svg";
    const RUTA_PRODUCTO = "productos";
    const PATH = 'productos';


    protected $appends = ['rutaProductoUrl'];

    public function getRutaProductoUrlAttribute()
    {
        if (
            isset($this->attributes['urlProducto']) &&
            isset($this->attributes['urlProducto'][0])
        ) {
            return url($this->attributes['urlProducto']);
        }
        return url(self::RUTA_PRODUCTO_DEFAULT);
    }


    public function tipoProducto()
    {
        return $this->belongsTo(TipoProducto::class, 'idTipoProducto');
    }

    
    public function distribucion()
    {
        return $this->hasOne(DistribucionProducto::class, 'idProducto');
    }


    public function distribuciones()
    {
        return $this->hasMany(DistribucionProducto::class, 'idProducto');
    }



    public function categoria()
    {
        return $this->belongsTo(Category::class, 'idCategoria');
    }

    public function historialPrecios()
    {
        return $this->hasMany(HistorialPrecio::class, 'idProducto');
    }

    public function medida()
    {
        return $this->belongsTo(Medida::class, 'idMedida');
    }


    public function detalleFactura()
    {
        return $this->hasMany(DetalleFactura::class, 'idProducto', 'id');
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'idMarca');
    }


    public function ultimoHistorialPrecio()
    {
        return $this->hasOne(HistorialPrecio::class, 'idProducto')
                    ->where('idCompany', KeyUtil::idCompany()) 
                    ->latestOfMany();
    }


    public function scopeCodigo($query, string $codigo, bool $parcial = false)
    {
        return $parcial
            ? $query->where('codigoProducto', 'LIKE', "%{$codigo}%")
            : $query->where('codigoProducto', $codigo);
    }
    
}
