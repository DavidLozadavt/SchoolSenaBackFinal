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
        Schema::create('observacionSolicitudIncLicPers', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->text('observacion')->nullable();

            $table->foreignId('idSolicitud')->references('id')->on('solicitudIncLicPersonas');

            $table->unsignedInteger('idUsuario');
            $table->foreign('idUsuario')->references('id')->on('usuario');

            $table->softDeletes();
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
        Schema::dropIfExists('observacionSolicitudIncLicPers');
    }
};
