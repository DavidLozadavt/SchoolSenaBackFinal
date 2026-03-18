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

            $table->unsignedBigInteger('idFicha')
                  ->comment('Relacion directa con la ficha');

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

            $table->text('observacion')->nullable();
            $table->boolean('condicionado')->nullable();

            $table->timestamps();

            /* Índices */
            $table->index('idAcudiente');
            $table->index('idFicha');
            $table->index('idPersona');
            $table->index('idCompany');
            $table->index('idGrado');

            /* Claves foráneas */
            $table->foreign('idAcudiente')
                  ->references('id')->on('persona');

            $table->foreign('idFicha')
                  ->references('id')->on('ficha');

            $table->foreign('idPersona')
                  ->references('id')->on('persona');

            $table->foreign('idCompany')
                  ->references('id')->on('empresa');

            $table->foreign('idGrado')
                  ->references('id')->on('grado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricula');
    }
};
