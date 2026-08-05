<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas ADITIVAS de presentación para el módulo "Planes de Mensajes".
 *
 * Solo se añaden columnas nullable/con default: ninguna columna existente se
 * modifica ni se elimina, y los planes ya creados siguen funcionando igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajesPlanes', function (Blueprint $table) {
            if (!Schema::hasColumn('mensajesPlanes', 'orden')) {
                $table->unsignedInteger('orden')->default(0)->after('activo');
            }
            if (!Schema::hasColumn('mensajesPlanes', 'recomendado')) {
                $table->boolean('recomendado')->default(false)->after('orden');
            }
            if (!Schema::hasColumn('mensajesPlanes', 'color')) {
                // Clase de color del tema (primary, success, warning, info, danger).
                $table->string('color', 30)->nullable()->after('recomendado');
            }
            if (!Schema::hasColumn('mensajesPlanes', 'etiqueta')) {
                $table->string('etiqueta', 60)->nullable()->after('color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mensajesPlanes', function (Blueprint $table) {
            foreach (['etiqueta', 'color', 'recomendado', 'orden'] as $columna) {
                if (Schema::hasColumn('mensajesPlanes', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
