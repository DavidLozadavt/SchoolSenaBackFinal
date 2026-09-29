<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('portafolioFichas', function (Blueprint $table) {
            $table->id();
            $table->text('descripcion');
            $table->unsignedBigInteger('idPortafolio');
            $table->unsignedBigInteger('idFicha');
            $table->timestamps();

            $table->foreign('idPortafolio')
                ->references('id')
                ->on('portafolio');

            $table->foreign('idFicha')
                ->references('id')
                ->on('ficha');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portafolioFichas');
    }
};