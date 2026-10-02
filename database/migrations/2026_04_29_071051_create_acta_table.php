<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('acta', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->enum('tipoActa', ['EQUIPO EJECUTOR', 'NORMAL']);
            $table->text('observacion')->nullable();
            $table->string('lugar');

            // Claves foráneas
            $table->unsignedInteger('idCiudad');
            $table->unsignedBigInteger('idFicha')->nullable();
            $table->unsignedInteger('idContrato');

            // Relaciones
            $table->foreign('idCiudad')->references('id')->on('ciudad');
            $table->foreign('idFicha')->references('id')->on('ficha');
            $table->foreign('idContrato')->references('id')->on('contrato');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('acta');
    }
};