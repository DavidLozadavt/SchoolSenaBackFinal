<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('novedadesAprendiz', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idusuario');
            $table->unsignedBigInteger('idmatricula');
            $table->enum('estado', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->enum('cambio', [
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
                'JUSTIFICADO',
                'EN FORMACION',
                'RETIRO VOLUNTARIO',
                'POR EVALUAR',
                'TRASLADADO',
                'APLAZADO',
                'DESERCION',
                'CONDICIONADO'
            ]);
            $table->text('observacion')->nullable();
            $table->timestamps();

            // Claves foráneas
            $table->foreign('idusuario')
                ->references('id')
                ->on('usuario')
                ->onDelete('cascade');

            $table->foreign('idmatricula')
                ->references('id')
                ->on('matricula')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('novedadesAprendiz');
    }
};