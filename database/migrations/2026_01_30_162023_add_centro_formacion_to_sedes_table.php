<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Agregar columna idCentroFormacion
            $table->unsignedBigInteger('idCentroFormacion')->nullable()->after('idEmpresa');
            
            // Agregar foreign key constraint
            $table->foreign('idCentroFormacion', 'sedes_idcentroformacion_foreign')
                  ->references('id')
                  ->on('centrosformacion')
                  ->onDelete('set null');
            
            // Agregar índice para mejor rendimiento
            $table->index('idCentroFormacion', 'sedes_idcentroformacion_index');
        });

        // IMPORTANTE: Agregar constraint UNIQUE compuesto
        // Una sede no puede tener el mismo nombre en el mismo centro de formación
        Schema::table('sedes', function (Blueprint $table) {
            $table->unique(['nombre', 'idCentroFormacion'], 'unique_nombre_por_centro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Eliminar constraint UNIQUE primero
            $table->dropUnique('unique_nombre_por_centro');
            
            // Eliminar foreign key
            $table->dropForeign('sedes_idcentroformacion_foreign');
            
            // Eliminar índice
            $table->dropIndex('sedes_idcentroformacion_index');
            
            // Eliminar columna
            $table->dropColumn('idCentroFormacion');
        });
    }
};