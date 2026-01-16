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
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();

            $table->string('distancia')->comment('Kilometraje de la ruta');
            $table->string('latitud');
            $table->string('longitud');
            $table->string('tiempoEstimado');
            $table->text('descripcion')->nullable();
            $table->double('precio', 20, 2);

            $table->unsignedInteger('idCiudadOrigen')->nullable();
            $table->foreign('idCiudadOrigen')->references('id')->on('ciudad');

            $table->unsignedInteger('idCiudadDestino')->nullable();
            $table->foreign('idCiudadDestino')->references('id')->on('ciudad');

            $table->foreignId('idLugar')->nullable()->references('id')->on('lugares');

            $table->foreignId('idRutaPadre')
                ->comment('Ruta padre para saber que es un registro de ruta hija')
                ->nullable()
                ->references('id')
                ->on('rutas');

            $table->foreignId('idRutaVuelta')
                ->comment('Saber cual es la ruta pareja y de esa forma saber cual es la de ida y la vuelta')
                ->nullable()
                ->references('id')
                ->on('rutas');

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
        Schema::dropIfExists('rutas');
    }
};
