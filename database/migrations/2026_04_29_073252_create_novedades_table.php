<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('novedadesActa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idacta');
            $table->unsignedBigInteger('idmatriculaAcademica');
            $table->text('observacion')->nullable();

            // Claves foráneas
            $table->foreign('idacta')->references('id')->on('acta');
            $table->foreign('idmatriculaAcademica')->references('id')->on('matriculaAcademica');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('novedadesActa');
    }
};