<?php

namespace App\Support;

use App\Models\Status;

/**
 * Reglas puras de eliminación del catálogo de actividades.
 * Una actividad solo puede borrarse si está INACTIVA y no tiene fichas asignadas.
 */
class ActividadEliminacionReglas
{
    public const MSG_ASIGNADA_FICHA = 'La actividad no puede eliminarse porque se encuentra asignada a una ficha.';
    public const MSG_NO_INACTIVA = 'La actividad debe estar inactiva antes de eliminarse.';
    public const MSG_ASIGNADA_CLASE = 'No se puede eliminar una actividad que está asignada a una clase. Quítela primero de la clase.';

    public static function esInactivo(?int $idEstado, ?string $nombreEstado = null): bool
    {
        if ($idEstado !== null && $idEstado === Status::ID_INACTIVO) {
            return true;
        }

        return strtoupper(trim((string) $nombreEstado)) === 'INACTIVO';
    }

    /**
     * Null si puede eliminarse. Mensaje si debe bloquearse.
     */
    public static function motivoNoEliminar(bool $inactivo, int $fichasAsignadas, bool $tieneAsignacionClase = false): ?string
    {
        if ($fichasAsignadas > 0) {
            return self::MSG_ASIGNADA_FICHA;
        }
        if ($tieneAsignacionClase) {
            return self::MSG_ASIGNADA_CLASE;
        }
        if (! $inactivo) {
            return self::MSG_NO_INACTIVA;
        }

        return null;
    }
}
