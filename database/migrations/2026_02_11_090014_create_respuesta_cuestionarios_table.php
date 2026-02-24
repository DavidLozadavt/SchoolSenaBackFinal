<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('respuestaCuestionarios')) {
            return;
        }
        Schema::create('respuestaCuestionarios', function (Blueprint $table) {
            $table->increments('id');
            $table->text('respuesta')->nullable();
            $table->double('puntaje')->nullable();
            $table->boolean('calificado')->nullable();
            $table->unsignedBigInteger('idCalificacion');
            $table->unsignedInteger('idPregunta');
            $table->unsignedInteger('idRespuesta')->nullable();

            $table->foreign('idCalificacion', 'respuestaCuestionarios_idcalificacion_foreign')
                ->references('id')->on('calificacionActividad');
            $table->foreign('idPregunta', 'respuestaCuestionarios_idpregunta_foreign')
                ->references('id')->on('preguntas');
            $table->foreign('idRespuesta', 'respuestaCuestionarios_idrespuesta_foreign')
                ->references('id')->on('respuestas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestaCuestionarios');
    }
};
