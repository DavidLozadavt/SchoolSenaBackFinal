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
        Schema::create('documentosAlcoholemia', function (Blueprint $table) {
            $table->id();

            $table->string('documento');

            $table->foreignId('idViaje')->references('id')->on('viajes');

            $table->unsignedInteger('idConductor');
            $table->foreign('idConductor')->references('id')->on(table: 'contrato');

            $table->text('observacion')->nullable();

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
        Schema::dropIfExists('documentosAlcoholemia');
    }
};
