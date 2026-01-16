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
        Schema::create('puntoVenta', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('imagenUrl', 255)->nullable();
            $table->enum('tipo', ['Tienda', 'Despacho', 'Servicios','otro'], 10)->nullable();
            $table->unsignedBigInteger('idSede')->nullable();
            $table->foreign('idSede')
                  ->references('id')
                  ->on('sedes'); 
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
        Schema::dropIfExists('punto_ventas');
    }
};
