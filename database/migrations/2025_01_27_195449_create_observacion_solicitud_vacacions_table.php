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
        Schema::create('observacionSolicitudVacaciones', function (Blueprint $table) {
            $table->id();

            $table->date('fecha');
            $table->text('observacion');
            
            $table->foreignId('idSolicitud')->references('id')->on('solicitudVacaciones');
            
            $table->unsignedInteger('idUsuario');
            $table->foreign('idUsuario')->references('id')->on('usuario');

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
        Schema::dropIfExists('observacion_solicitud_vacacions');
    }
};
