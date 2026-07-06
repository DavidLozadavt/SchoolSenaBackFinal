<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('portafolioDocumentos', function (Blueprint $table) {
            $table->id();
            $table->text('descripcion');
            $table->string('urlDocumento', 500);
            $table->unsignedBigInteger('idPortafolioFichas');
            $table->timestamps();

            $table->foreign('idPortafolioFichas')
                ->references('id')
                ->on('portafolioFichas');
        });
    }

    public function down()
    {
        Schema::dropIfExists('portafolioDocumentos');
    }
};