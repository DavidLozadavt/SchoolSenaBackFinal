<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Hermano extends Model
{
    protected $table = 'hermano';
 
    protected $fillable = [
        'nombre',
        'celularContacto',
        'edad',
        'nombreContactoF',
        'celularContactoF',
        'pago',
        'saldo',
        'formaPago',
        'email',
        'celularEmergencia',
        'parentesco',
        'observacion',
        'qr_token', // 🔥 IMPORTANTE
    ];
 
    protected $casts = [
        'id' => 'integer',
        'edad' => 'integer',
        'pago' => 'float',
        'saldo' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
 
    // 🔥 RELACIÓN PRO
    public function items()
    {
        return $this->belongsToMany(
            Item::class,
            'ejecucionitem',
            'idHermano',
            'idItem'
        )->withPivot(['recibido', 'fecha_scan']);
    }
 
    public function ejecuciones()
    {
        return $this->hasMany(EjecucionItem::class, 'idHermano');
    }
}
