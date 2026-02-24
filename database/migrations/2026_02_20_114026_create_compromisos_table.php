<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('compromisos', function (Blueprint $table) {
            $table->id();

            $table->string('observacion')->nullable();
            $table->string('urlDocumento')->nullable();
            $table->date('fecha');

            $table->unsignedBigInteger('idAnotacionesDisciplinarias');
            $table->unsignedInteger('idDocente');

            $table->boolean('cumplido')->default(false);

            $table->timestamps();

            $table->foreign('idAnotacionesDisciplinarias')
                  ->references('id')
                  ->on('anotacionesDisciplinarias');

            $table->foreign('idDocente')
                  ->references('id')
                  ->on('contrato');
        });
    }

    public function down()
    {
        Schema::dropIfExists('compromisos');
    }
};