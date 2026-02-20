<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->text('tituloActividad');
            $table->text('descripcionActividad')->nullable();
            $table->string('pathDocumentoActividad', 255)->nullable();
            $table->string('autor', 255)->nullable();
            $table->unsignedInteger('idTipoActividad');
            $table->unsignedBigInteger('idMateria');
            $table->unsignedInteger('idEstado');
            $table->unsignedInteger('idCompany');
            $table->unsignedInteger('idPersona')->nullable();
            $table->unsignedBigInteger('idClasificacion')->nullable();
            $table->text('estrategia')->nullable();
            $table->text('entregables')->nullable();

            $table->foreign('idTipoActividad', 'actividades_idtipoactividad_foreign')
                ->references('id')->on('tipo_actividades');
            $table->foreign('idMateria', 'actividades_idmateria_foreign')
                ->references('id')->on('materia');
            $table->foreign('idEstado', 'actividades_idestado_foreign')
                ->references('id')->on('estado');
            $table->foreign('idCompany', 'actividades_idcompany_foreign')
                ->references('id')->on('empresa');
            $table->foreign('idPersona', 'actividades_persona_FK')
                ->references('id')->on('persona');
            $table->foreign('idClasificacion', 'actividades_clasificacionActividad_FK')
                ->references('id')->on('clasificacionactividad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
                