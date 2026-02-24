<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justificacionInasistencia', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idAsistencia');
            $table->unsignedBigInteger('idExcusa');
            $table->unsignedBigInteger('idMatriculaAcademica');

            $table->enum('estado', [
                'ACTIVO',
                'INACTIVO',
                'OCULTO',
                'PENDIENTE',
                'RECHAZADO',
                'APROBADO',
                'CANCELADO',
                'REPROBADO',
                'CERRADO',
                'ACEPTADO',
                'LEIDO',
                'EN ESPERA',
                'INSCRIPCION',
                'MATRICULADO',
                'ABIERTO',
                'EN CURSO',
                'POR ACTUALIZAR',
                'CURSANDO',
                'ENTREVISTA',
                'SIN ENTREVISTA',
                'JUSTIFICADO'
            ])->default('PENDIENTE');

            $table->unsignedInteger('idPersona')
                  ->nullable()
                  ->comment('Persona administrativa que aprueba la excusa');

            $table->text('observacion')
                  ->nullable()
                  ->comment('Observacion que hace la persona encargada');

            $table->timestamps();

            // 🔹 Foreign Keys
            $table->foreign('idAsistencia')
                  ->references('id')
                  ->on('asistencia');

            $table->foreign('idExcusa')
                  ->references('id')
                  ->on('excusa');

            $table->foreign('idMatriculaAcademica')
                  ->references('id')
                  ->on('matriculaAcademica');

            $table->foreign('idPersona')
                  ->references('id')
                  ->on('persona');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificacionInasistencia');
    }
};