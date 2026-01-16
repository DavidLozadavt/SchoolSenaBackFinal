<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentoContratosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documento_contratos', function (Blueprint $table) {
            $table->increments('id');

            $table->timestamp('fechaCarga');
            $table->string('ruta');

            $table->unsignedInteger('idContrato');
            $table->foreign('idContrato')->references('id')->on('contrato');

            $table->unsignedInteger('idTipoDocumento');
            $table->foreign('idTipoDocumento')->references('id')->on('tipoDocumento');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documento_contratos');
    }
}
