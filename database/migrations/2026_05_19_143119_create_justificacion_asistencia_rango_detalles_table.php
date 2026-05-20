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
        Schema::create('justificacionAsistenciaRangoDetalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idJustificacionAsistenciaRango');
            $table->unsignedBigInteger('idHorarioMateria');
            $table->unsignedBigInteger('idFicha')->nullable();
            $table->unsignedBigInteger('idContratoInstructor')->nullable();
            $table->unsignedInteger('idPersonaInstructor')->nullable();
            $table->string('estado')->default('PENDIENTE');
            $table->unsignedInteger('idPersonaAutoriza')->nullable();
            $table->date('fechaRespuesta')->nullable();
            $table->text('observacionInstructor')->nullable();
            $table->timestamps();

            $table->foreign('idJustificacionAsistenciaRango', 'fk_jar_detalles_rango')->references('id')->on('justificacionAsistenciaRangos')->onDelete('cascade');
            $table->foreign('idHorarioMateria', 'fk_jar_detalles_horario')->references('id')->on('horarioMateria')->onDelete('cascade');
            $table->foreign('idPersonaInstructor', 'fk_jar_detalles_instructor')->references('id')->on('persona')->onDelete('set null');
            $table->foreign('idPersonaAutoriza', 'fk_jar_detalles_autoriza')->references('id')->on('persona')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('justificacionAsistenciaRangoDetalles');
    }
};
