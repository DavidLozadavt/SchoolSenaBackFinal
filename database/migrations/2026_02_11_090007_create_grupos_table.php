<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla grupos para ambiente virtual (grupos por RAP/ficha).
     * Requerida por: asignacionparticipantes, calificacionActividad, asignacioncomentarios.
     * Estructura según SQL existente con camelCase.
     */
    public function up(): void
    {
        if (Schema::hasTable('grupos')) {
            return;
        }

        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombreGrupo');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('INACTIVO');
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('cantidadParticipantes');
            $table->foreignId('idTipoGrupo')->references('id')->on('tipoGrupo');
            $table->foreignId('idAsignacionPeriodoProgramaJornada')->references('id')->on('ficha');
            $table->foreignId('idGradoMateria')->references('id')->on('gradoMateria');
            $table->timestamps();

            $table->index('idTipoGrupo');
            $table->index('idAsignacionPeriodoProgramaJornada');
            $table->index('idGradoMateria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
