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
        Schema::create('gC', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('idContrato');
            $table->unsignedBigInteger('idRmi');

            $table->enum('estado', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'])
                  ->default('PENDIENTE');

            $table->text('observacion')->nullable();

            

            // 🔗 Relaciones
            $table->foreign('idContrato')
                  ->references('id')
                  ->on('contrato');

            $table->foreign('idRmi')
                  ->references('id')
                  ->on('rmi');


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
        Schema::dropIfExists('gC');
    }
};
