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
        Schema::create('documentoGC', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idGC');

            $table->enum('estado', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'])
                  ->default('PENDIENTE');

            $table->text('observacion')->nullable();
            $table->string('urlDocumento');


            // 🔗 Relación con GC
            $table->foreign('idGC')
                  ->references('id')
                  ->on('gC');

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
        Schema::dropIfExists('documentoGC');
    }
};
