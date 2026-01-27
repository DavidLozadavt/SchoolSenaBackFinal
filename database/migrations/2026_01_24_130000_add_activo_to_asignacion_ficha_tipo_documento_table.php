<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el campo 'activo' a la tabla asignacion_ficha_tipo_documento.
     * Permite marcar qué documentos están autorizados/activos para cada ficha.
     */
    public function up(): void
    {
        if (!Schema::hasTable('asignacion_ficha_tipo_documento')) {
            return; // La tabla no existe aún, se agregará en la migración original
        }
        if (Schema::hasColumn('asignacion_ficha_tipo_documento', 'activo')) {
            return; // Ya existe el campo
        }
        Schema::table('asignacion_ficha_tipo_documento', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('idTipoDocumento')
                ->comment('true = autorizado/activo, false = inactivo');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('asignacion_ficha_tipo_documento')) {
            return;
        }
        if (!Schema::hasColumn('asignacion_ficha_tipo_documento', 'activo')) {
            return;
        }
        Schema::table('asignacion_ficha_tipo_documento', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
