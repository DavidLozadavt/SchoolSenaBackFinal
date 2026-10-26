<?php

namespace App\Support;

/**
 * Un RAP pertenece a una ficha por el vínculo horarioMateria (ficha + gradoMateria),
 * aunque ya no tenga día/hora calendarizados.
 *
 * El último registro de esa pareja no debe borrarse: se convierte en placeholder.
 * Contar horarios de otras fichas (mismo gradoMateria) NO autoriza borrar el de esta ficha.
 */
class HorarioMateriaVinculoReglas
{
    public static function debeConservarPlaceholder(int $horariosDeFichaYRap): bool
    {
        return $horariosDeFichaYRap <= 1;
    }
}
