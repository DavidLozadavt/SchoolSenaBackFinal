<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormularioRespuestasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('formulario_respuestas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idFormulario');
            $table->unsignedInteger('idUser')->nullable(); // Anónimo si es null
            $table->string('ipAddress', 45)->nullable();
            $table->json('respuestas'); // [{idPregunta: 1, valor: "texto"}]
            $table->timestamps();

            $table->foreign('idFormulario')->references('id')->on('formularios')->onDelete('cascade');
            $table->foreign('idUser')->references('id')->on('usuario')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('formulario_respuestas');
    }
}
