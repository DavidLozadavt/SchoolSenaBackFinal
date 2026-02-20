<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE actividades MODIFY COLUMN tipoActividad ENUM('sin evidencia', 'con evidencia', 'cuestionario') DEFAULT 'sin evidencia'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE actividades MODIFY COLUMN tipoActividad ENUM('sin evidencia', 'con evidencia') DEFAULT 'sin evidencia'");
    }
};
