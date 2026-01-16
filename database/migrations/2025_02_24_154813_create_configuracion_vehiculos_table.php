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
        Schema::create('configuracionVehiculos', function (Blueprint $table) {
            $table->id();

            $table->integer('puesto');

            $table->unsignedInteger('idVehiculo')->nullable();
            $table->foreign('idVehiculo')->references('id')->on('vehiculo');

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
        Schema::dropIfExists('configuracionVehiculos');
    }
};
