<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('justificacionAsistenciaRangos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idPersonaAprendiz');
            $table->unsignedBigInteger('idMatricula')->nullable();
            $table->date('fechaInicial');
            $table->date('fechaFinal');
            $table->string('tipoExcusa');
            $table->text('observacion');
            $table->string('archivoSoporte')->nullable();
            $table->string('estado')->default('PENDIENTE');
            $table->unsignedInteger('idPersonaAutoriza')->nullable();
            $table->date('fechaRespuesta')->nullable();
            $table->text('observacionInstructor')->nullable();
            $table->timestamps();

            $table->foreign('idPersonaAprendiz')->references('id')->on('persona');
            $table->foreign('idMatricula')->references('id')->on('matricula');
            $table->foreign('idPersonaAutoriza')->references('id')->on('persona');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('justificacionAsistenciaRangos');
    }
};
