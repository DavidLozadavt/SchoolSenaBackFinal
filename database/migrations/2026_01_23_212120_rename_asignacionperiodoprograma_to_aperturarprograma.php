<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * NO-OP: El usuario solicitó NO renombrar la tabla asignacionPeriodoPrograma.
     * Esta migración no hace nada para mantener la tabla con su nombre original.
     */
    public function up(): void
    {
        // No hacer nada - mantener asignacionPeriodoPrograma
    }

    public function down(): void
    {
        // No hacer nada
    }
};
