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
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('jefeInmediato', 100);
            $table->text('descripcion');
            $table->string('urlImagen', 100);
            $table->string('direccion', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono', 100)->nullable();
            $table->string('celular', 100)->nullable();
            $table->unsignedInteger('idResponsable')->nullable();
            $table->foreign('idResponsable')->references('id')->on('usuario');
            $table->unsignedInteger('idEmpresa');
            $table->foreign('idEmpresa')->references('id')->on('empresa');
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
        Schema::dropIfExists('sedes');
    }
};
