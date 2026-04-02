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
        Schema::create('actividadesContrato', function (Blueprint $table) {
            $table->id();

            $table->text('obligaciones');
            $table->text('accionesRealizadas');
            $table->text('evidencias');
            $table->unsignedInteger('idContrato');
            
            $table->foreign('idContrato')->references('id')->on('contrato');
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
        Schema::dropIfExists('actividadesContrato');
    }
};
