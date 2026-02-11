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
        Schema::create('asignacion_contrato_programa', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idContrato');
            $table->unsignedBigInteger('idPrograma');
            $table->timestamps();

            $table->foreign('idContrato')->references('id')->on('contrato')->onDelete('cascade');
            $table->foreign('idPrograma')->references('id')->on('programa')->onDelete('cascade');
            
            $table->unique(['idContrato', 'idPrograma'], 'contrato_programa_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asignacion_contrato_programa');
    }
};
