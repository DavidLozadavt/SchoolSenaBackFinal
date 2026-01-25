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
        Schema::create('asignacion_contrato_area_conocimiento', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idContrato');
            $table->unsignedBigInteger('idAreaConocimiento');
            $table->timestamps();

            $table->foreign('idContrato')->references('id')->on('contrato')->onDelete('cascade');
            $table->foreign('idAreaConocimiento')->references('id')->on('area_conocimiento')->onDelete('cascade');
            
            $table->unique(['idContrato', 'idAreaConocimiento'], 'contrato_area_conocimiento_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asignacion_contrato_area_conocimiento');
    }
};
