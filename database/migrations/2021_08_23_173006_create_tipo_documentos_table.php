<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTipoDocumentosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipoDocumento', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tituloDocumento');
            $table->text('descripcion');
            $table->foreign('idEstado')->references('id')->on('estado');
            $table->unsignedInteger('idEstado');
            $table->foreign('idProceso')->references('id')->on('proceso');
            $table->unsignedInteger('idProceso');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipoDocumento');
    }
}
