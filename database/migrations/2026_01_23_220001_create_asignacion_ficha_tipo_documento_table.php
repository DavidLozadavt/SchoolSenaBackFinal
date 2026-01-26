<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asignación de tipos de documento por ficha (GradoPrograma).
     * Cada ficha puede tener distintos documentos; se asignan con toggles y GUARDAR.
     */
    public function up(): void
    {
        if (!Schema::hasTable('gradoPrograma')) {
            throw new \RuntimeException(
                'Ejecute primero la migración create_grado_programa. ' .
                'Ej.: php artisan migrate --path=database/migrations/2026_01_15_203258_create_grado_programa_table.php --force'
            );
        }
        if (Schema::hasTable('asignacion_ficha_tipo_documento')) {
            return;
        }
        Schema::create('asignacion_ficha_tipo_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idGradoPrograma')->constrained('gradoPrograma')->onDelete('cascade');
            $table->unsignedInteger('idTipoDocumento');
            $table->boolean('activo')->default(true)->comment('true = autorizado/activo, false = inactivo');
            $table->timestamps();

            $table->foreign('idTipoDocumento')->references('id')->on('tipoDocumento')->onDelete('cascade');
            $table->unique(['idGradoPrograma', 'idTipoDocumento'], 'asig_ficha_tipodoc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_ficha_tipo_documento');
    }
};
