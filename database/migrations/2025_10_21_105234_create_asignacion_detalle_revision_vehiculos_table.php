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
        Schema::create('asignacionDetalleRevisionVehiculo', function (Blueprint $table) {
          $table->id();
            $table->unsignedBigInteger('idDetalle')->nullable();
            $table->unsignedBigInteger('idVehiculo')->nullable();
            $table->unsignedBigInteger('idUser')->nullable();
            $table->unsignedBigInteger('idViaje')->nullable();
            $table->dateTime('fechaRevision')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO', 'PENDIENTE'])->default('ACTIVO');
            $table->timestamps();

            $table->foreign('idDetalle')
                  ->references('id')
                  ->on('detalle_revision')
                  ->onDelete('cascade');
            
            $table->foreign('idVehiculo')
                  ->references('id')
                  ->on('vehiculo')
                  ->onDelete('cascade');
            
            $table->foreign('idUser')
                  ->references('id')
                  ->on('usuario')
                  ->onDelete('cascade');
            
            $table->foreign('idViaje')
                  ->references('id')
                  ->on('viajes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asignacionDetalleRevisionVehiculo');
    }
};
