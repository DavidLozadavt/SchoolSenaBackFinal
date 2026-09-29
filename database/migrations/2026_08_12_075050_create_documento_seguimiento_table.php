<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('documentoSeguimiento', function (Blueprint $table) {
            $table->id(); //
            $table->string('documentoUrl', 255)->nullable(false);
            $table->string('nombre_documento', 255)->nullable(false);
            $table->enum('estado', ['PENDIENTE', 'APROBADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->text('observacion')->nullable(true);
            $table->unsignedBigInteger('idseguimiento'); // Clave foránea

            // Clave foránea
            $table->foreign('idseguimiento')
                  ->references('id')
                  ->on('seguimientoAprendiz')
                  ->onDelete('no action')
                  ->onUpdate('no action');
        });
    }

    public function down()
    {
        Schema::dropIfExists('documentoSeguimiento');
    }
};