<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormularioPreguntasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('formulario_preguntas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idFormulario');
            $table->enum('tipo', [
                'texto_corto',
                'texto_largo',
                'opcion_multiple',
                'casillas',
                'desplegable',
                'escala_lineal',
                'fecha',
                'hora'
            ]);
            $table->text('titulo');
            $table->text('descripcion')->nullable();
            $table->boolean('esObligatoria')->default(false);
            $table->integer('orden');
            $table->json('configuracion')->nullable();
            $table->timestamps();

            $table->foreign('idFormulario')->references('id')->on('formularios')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('formulario_preguntas');
    }
}
