<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculaAcademica', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idFicha');
            $table->unsignedBigInteger('idGradoMateria')->nullable();
            $table->unsignedBigInteger('idMatricula');

            $table->enum('estado', [
                'ACTIVO','INACTIVO','OCULTO','PENDIENTE','RECHAZADO','APROBADO',
                'CANCELADO','REPROBADO','CERRADO','ACEPTADO','LEIDO','EN ESPERA',
                'INSCRIPCION','MATRICULADO','ABIERTO','EN CURSO','POR ACTUALIZAR',
                'CURSANDO','ENTREVISTA','SIN ENTREVISTA','JUSTIFICADO',
                'EN FORMACION','RETIRO VOLUNTARIO','POR EVALUAR','TRASLADADO'
            ])->nullable();

            $table->timestamps();

            $table->unsignedInteger('idEvaluador')->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('idMateria')->nullable();
            $table->float('notaParcial')->nullable();

            // Relaciones
            $table->foreign('idFicha')
                ->references('id')
                ->on('ficha');

            $table->foreign('idGradoMateria', 'matriculaAcademica_gradoMateria_FK')
                ->references('id')
                ->on('gradomateria');

            $table->foreign('idMatricula')
                ->references('id')
                ->on('matricula');

            $table->foreign('idEvaluador', 'matriculaAcademica_persona_FK')
                ->references('id')
                ->on('persona');

            $table->foreign('idMateria', 'matriculaAcademica_materia_FK')
                ->references('id')
                ->on('materia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculaAcademica');
    }
};
