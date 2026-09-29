<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planeacion_pedagogica', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idContrato');
            $table->unsignedBigInteger('idFicha')->nullable();
            $table->unsignedInteger('idCompany')->nullable();
            $table->date('semanaInicio');
            $table->date('semanaFin');
            $table->enum('nivel', ['PRIMARIA', 'BACHILLER'])->default('PRIMARIA');
            $table->string('grado', 80)->nullable();
            $table->string('periodo', 120)->nullable();
            $table->string('institucion', 255)->nullable();
            $table->string('docenteNombre', 255)->nullable();
            $table->text('proposito')->nullable();
            $table->string('temaIntegrador', 500)->nullable();
            $table->string('metodologia', 500)->nullable();
            $table->enum('estado', ['BORRADOR', 'ENVIADA', 'APROBADA', 'EN_EJECUCION', 'CERRADA', 'INCOMPLETA'])->default('BORRADOR');
            $table->text('reflexionDocente')->nullable();
            $table->text('motivoIncompleta')->nullable();
            $table->string('pdfPath', 500)->nullable();
            $table->timestamp('enviadoAt')->nullable();
            $table->string('coordinadorEmail', 255)->nullable();
            $table->timestamps();

            $table->index(['idContrato', 'semanaInicio']);
            $table->foreign('idFicha')->references('id')->on('ficha')->nullOnDelete();
        });

        Schema::create('planeacion_pedagogica_clase', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idPlaneacion');
            $table->unsignedBigInteger('idHorarioMateria')->nullable();
            $table->unsignedTinyInteger('idDia')->nullable();
            $table->string('diaNombre', 40)->nullable();
            $table->string('asignatura', 255)->nullable();
            $table->string('horaInicial', 20)->nullable();
            $table->string('horaFinal', 20)->nullable();
            $table->string('tema', 500)->nullable();
            $table->text('aprendizajeEsperado')->nullable();
            $table->text('preguntaProblematizadora')->nullable();
            $table->text('saberesPrevios')->nullable();
            $table->text('estandar')->nullable();
            $table->text('dba')->nullable();
            $table->text('competencia')->nullable();
            $table->text('evidencia')->nullable();
            $table->text('criterios')->nullable();
            $table->string('instrumento', 255)->nullable();
            $table->text('recursos')->nullable();
            $table->text('refuerzo')->nullable();
            $table->text('profundizacion')->nullable();
            $table->text('actividadPractica')->nullable();
            $table->json('secuencia')->nullable();
            $table->string('tallerTitulo', 255)->nullable();
            $table->text('tallerContenido')->nullable();
            $table->dateTime('tallerInicio')->nullable();
            $table->dateTime('tallerFin')->nullable();
            $table->boolean('ejecutada')->default(false);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('idPlaneacion')
                ->references('id')
                ->on('planeacion_pedagogica')
                ->cascadeOnDelete();
            $table->index('idHorarioMateria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planeacion_pedagogica_clase');
        Schema::dropIfExists('planeacion_pedagogica');
    }
};
