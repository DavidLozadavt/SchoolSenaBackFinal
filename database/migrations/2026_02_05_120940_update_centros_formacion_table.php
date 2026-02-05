<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renombrar tabla
        if (Schema::hasTable('centrosformacion')) {
            Schema::rename('centrosformacion', 'centroFormacion');
        }


        // 2. Renombrar columna correosubdirector
        DB::statement("
            ALTER TABLE centroFormacion
            CHANGE correosubdirector correoSubdirector VARCHAR(255) NULL
        ");

        // 3. Permitir NULL en columnas
        DB::statement("
            ALTER TABLE centroFormacion
            MODIFY telefono VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE centroFormacion
            MODIFY correo VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE centroFormacion
            MODIFY correoSubdirector VARCHAR(255) NULL
        ");

        // 4. Agregar columna foto
        Schema::table('centroFormacion', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('correoSubdirector');
        });
    }

    public function down(): void
    {
        // Eliminar columna foto
        Schema::table('centroFormacion', function (Blueprint $table) {
            $table->dropColumn('foto');
        });

        // Restaurar nombre de columna
        DB::statement("
            ALTER TABLE centroFormacion
            CHANGE correoSubdirector correosubdirector VARCHAR(255) NOT NULL
        ");

        // Restaurar columnas NOT NULL
        DB::statement("
            ALTER TABLE centroFormacion
            MODIFY telefono VARCHAR(255) NOT NULL
        ");

        DB::statement("
            ALTER TABLE centroFormacion
            MODIFY correo VARCHAR(255) NOT NULL
        ");

        // Restaurar nombre de tabla
        Schema::rename('centroFormacion', 'centrosformacion');
    }
};
