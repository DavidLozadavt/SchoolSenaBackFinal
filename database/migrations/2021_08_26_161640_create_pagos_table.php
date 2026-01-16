<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->increments('id');
            $table->date('fechaPago');
            $table->date('fechaReg');
            $table->float('valor');
            $table->integer('numeroFact');
            $table->float('excedente');
            $table->unsignedInteger('idEstado');
            $table->foreign('idEstado')->references('id')->on('estado');
            $table->unsignedInteger('idTransaccion');
            $table->foreign('idTransaccion')->references('id')->on('transaccion');
            $table->unsignedInteger('idMedioPago');
            $table->foreign('idMedioPago')->references('id')->on('medioPago');
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
        Schema::dropIfExists('pagos');
    }
}
