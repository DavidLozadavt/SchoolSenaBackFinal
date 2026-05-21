<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormularioOpcionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('formulario_opciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idFormularioPregunta');
            $table->string('texto', 500);
            $table->integer('orden');
            $table->timestamps();

            $table->foreign('idFormularioPregunta')->references('id')->on('formulario_preguntas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('formulario_opciones');
    }
}
