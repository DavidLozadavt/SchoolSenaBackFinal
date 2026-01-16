<?php

use App\Enums\EstadosViaje;
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
        Schema::create('viajes', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('idVehiculo')->nullable();
            $table->foreign('idVehiculo')->references('id')->on('vehiculo');

            $table->unsignedInteger('idConductor')->nullable();
            $table->foreign('idConductor')->references('id')->on('contrato');

            $table->foreignId('idRuta')->references('id')->on('rutas');

            $table->enum('estado', EstadosViaje::getValues())->default(EstadosViaje::PENDIENTE);

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
        Schema::dropIfExists('viajes');
    }
};
