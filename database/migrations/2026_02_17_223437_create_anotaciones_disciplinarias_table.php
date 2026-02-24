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
        Schema::create('anotacionesDisciplinarias', function (Blueprint $table) {
            $table->id();
            $table->string('observacion')->nullable();
            $table->string('urlDocumento')->nullable();
            $table->date('fecha');
            $table->foreignId('idEstudiante')->references('id')->on('matricula');
            $table->unsignedInteger('idDocente');
            $table->foreign('idDocente')->references('id')->on('contrato');
            $table->enum('gradoAnotacion', ['LEVE', 'MODERADA', 'GRAVE', 'MUYGRAVE'])->default('LEVE');
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
        Schema::dropIfExists('anotacionesDisciplinarias');
    }
};
