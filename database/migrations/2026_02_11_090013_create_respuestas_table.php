<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('respuestas')) {
            return;
        }
        Schema::create('respuestas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('descripcionRespuesta', 255);
            $table->boolean('chkCorrecta');
            $table->double('puntaje');
            $table->unsignedInteger('idPregunta');

            $table->foreign('idPregunta', 'respuestas_idpregunta_foreign')
                ->references('id')->on('preguntas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas');
    }
};
