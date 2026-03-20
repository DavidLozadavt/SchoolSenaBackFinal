<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relaciona actividades con grupos.
     * Cuando un aprendiz se une al grupo, recibe automáticamente las actividades asignadas.
     */
    public function up(): void
    {
        $tableName = 'asignacionActividadGrupo';
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idActividad');
            $table->unsignedBigInteger('idGrupo');
            $table->dateTime('fechaInicial')->nullable();
            $table->dateTime('fechaFinal')->nullable();
            $table->unsignedInteger('idPersona')->nullable();
            $table->timestamps();

            $table->foreign('idActividad')->references('id')->on('actividades');
            $table->foreign('idGrupo')->references('id')->on('grupos');
            $table->foreign('idPersona')->references('id')->on('persona');

            $table->unique(['idActividad', 'idGrupo'], 'asignacion_actividad_grupo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacionActividadGrupo');
    }
};
