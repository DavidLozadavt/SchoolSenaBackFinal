<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idViaje')->references('id')->on('viajes');

            $table->foreignId('idTercero')->references('id')->on('tercero');

            $table->foreignId('idConfiguracionVehiculo')->references('id')->on('configuracionVehiculos');

            $table->foreignId('idAgendaViaje')->references('id')->on('agendarViajes');

            $table->foreignId('idRuta')->references('id')->on('rutas');

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
        Schema::dropIfExists('tickets');
    }
};
