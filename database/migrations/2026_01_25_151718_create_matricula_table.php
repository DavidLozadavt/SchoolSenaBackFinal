<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matricula', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedInteger('idAcudiente')->nullable();
            $table->unsignedBigInteger('idAsignacionPeriodoProgramaJornada')
                ->comment('Relacion directa con la asignacionPeriodoProgramaJornada para obtener la jornada y su asignacion del periodo');
            $table->unsignedInteger('idPersona')
                ->comment('Relación con el estudiante de la matricula');
            $table->unsignedInteger('idCompany');
            $table->unsignedBigInteger('idGrado');
            $table->enum('estado', [
                'ACTIVO','INACTIVO','OCULTO','PENDIENTE','RECHAZADO','APROBADO',
                'CANCELADO','REPROBADO','CERRADO','ACEPTADO','LEIDO','EN ESPERA',
                'INSCRIPCION','MATRICULADO','ABIERTO','EN CURSO','POR ACTUALIZAR',
                'CURSANDO','ENTREVISTA','SIN ENTREVISTA','JUSTIFICADO','EN FORMACION',
                'RETIRO VOLUNTARIO','POR EVALUAR','TRASLADADO','APLAZADO','DESERCION',
                'CONDICIONADO'
            ]);
            $table->timestamps();
            $table->text('observacion')->nullable();
            $table->boolean('condicionado')->nullable();

            $table->index('idAcudiente');
            $table->index('idAsignacionPeriodoProgramaJornada');
            $table->index('idPersona');
            $table->index('idCompany');
            $table->index('idGrado');

            $table->foreign('idAcudiente')->references('id')->on('persona');
            $table->foreign('idAsignacionPeriodoProgramaJornada')->references('id')->on('asignacionPeriodoProgramaJornada');
            $table->foreign('idPersona')->references('id')->on('persona');
            $table->foreign('idCompany')->references('id')->on('empresa');
            $table->foreign('idGrado')->references('id')->on('grado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricula');
    }
};
