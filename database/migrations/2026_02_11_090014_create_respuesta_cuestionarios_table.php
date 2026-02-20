<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('respuesta_cuestionarios')) {
            return;
        }
        Schema::create('respuesta_cuestionarios', function (Blueprint $table) {
            $table->increments('id');
            $table->text('respuesta')->nullable();
            $table->double('puntaje')->nullable();
            $table->boolean('calificado')->nullable();
            $table->unsignedBigInteger('idCalificacion');
            $table->unsignedInteger('idPregunta');
            $table->unsignedInteger('idRespuesta')->nullable();

            $table->foreign('idCalificacion', 'respuesta_cuestionarios_idcalificacion_foreign')
                ->references('id')->on('calificacionactividad');
            $table->foreign('idPregunta', 'respuesta_cuestionarios_idpregunta_foreign')
                ->references('id')->on('preguntas');
            $table->foreign('idRespuesta', 'respuesta_cuestionarios_idrespuesta_foreign')
                ->references('id')->on('respuestas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuesta_cuestionarios');
    }
};
