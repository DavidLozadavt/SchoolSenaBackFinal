<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('asistenciaActa', function (Blueprint $table) {
            $table->id();
            $table->string('dependencia');
            $table->enum('aprueba', ['SI', 'NO']);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('idActa');
            $table->unsignedInteger('idContrato');
            $table->timestamps();

            // Claves foráneas
            $table->foreign('idActa')
                ->references('id')
                ->on('acta');

            $table->foreign('idContrato')
                ->references('id')
                ->on('contrato');
        });
    }

    public function down()
    {
        Schema::dropIfExists('asistenciaActa');
    }
};