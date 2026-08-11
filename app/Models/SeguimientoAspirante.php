<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoAspirante extends Model
{
    use HasFactory;

    public static $snakeAttributes = false;
    
    public $timestamps = true;
    
    protected $table = "seguimientoAspirantes";

    protected $guarded = [];

    /**
     * ¿El usuario autenticado ve los aspirantes de TODOS?
     * Solo el Administrador VT; el resto ve únicamente los que importó.
     */
    public static function puedeVerTodos(): bool
    {
        try {
            return (bool) auth()->user()?->hasRole('ADMINISTRADOR VT');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Aspirantes visibles para el usuario autenticado. Punto único de la regla
     * de propiedad: lo usan tanto Seguimiento de Aspirantes como Solicitudes de
     * Inscripción.
     */
    public function scopeVisibles($query)
    {
        return $query->delUsuario(auth()->id(), static::puedeVerTodos());
    }

    /**
     * Limita la consulta a los aspirantes importados por un usuario.
     *
     * Los registros anteriores a esta funcionalidad tienen `importadoPorUserId`
     * en NULL (sin dueño) y solo son visibles para quien puede verlo todo.
     *
     * @param  bool  $verTodos  true para el Administrador VT (sin filtro).
     */
    public function scopeDelUsuario($query, ?int $userId, bool $verTodos = false)
    {
        if ($verTodos) {
            return $query;
        }

        return $query->where('importadoPorUserId', $userId);
    }
}
