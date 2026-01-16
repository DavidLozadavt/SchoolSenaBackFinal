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
        Schema::create('centrosformacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('direccion');
            $table->string('telefono');
            $table->string('correo');
            $table->string('subdirector');
            $table->string('correosubdirector');

            //ForeingKey necesarias Regional y Departamentos:
            $table->unsignedBigInteger('idRegional');
            $table->unsignedInteger('idCiudad');
            //Definición de las llaves foraneas:
            $table->foreign('idRegional', 'centrosformacion_idregional_foreign')
                ->references('id')->on('regional');
            $table->foreign('idCiudad', 'centrosformacion_idciudad_foreign')
                ->references('id')->on('ciudad');

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
        Schema::dropIfExists('centrosformacion');
    }
};
