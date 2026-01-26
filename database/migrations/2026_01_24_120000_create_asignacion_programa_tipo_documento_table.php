<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asignación de tipos de documento por programa con estado activo/inactivo.
     * Permite marcar qué documentos están autorizados/activos para cada programa.
     */
    public function up(): void
    {
        if (!Schema::hasTable('programa')) {
            throw new \RuntimeException(
                'Ejecute primero la migración create_programa_table.'
            );
        }
        if (Schema::hasTable('asignacion_programa_tipo_documento')) {
            return;
        }
        Schema::create('asignacion_programa_tipo_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idPrograma')->constrained('programa')->onDelete('cascade');
            $table->unsignedInteger('idTipoDocumento');
            $table->boolean('activo')->default(true)->comment('true = autorizado/activo, false = inactivo');
            $table->timestamps();

            $table->foreign('idTipoDocumento')->references('id')->on('tipoDocumento')->onDelete('cascade');
            $table->unique(['idPrograma', 'idTipoDocumento'], 'asig_programa_tipodoc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_programa_tipo_documento');
    }
};
