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
        Schema::create('detalleRmi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idRmi')->constrained('rmi')->onDelete('cascade');
            $table->unsignedBigInteger('idHorarioMateria');
            $table->foreign('idHorarioMateria')->references('id')->on('horarioMateria');
            $table->enum('estado', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'])->default('PENDIENTE');
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
        Schema::dropIfExists('detalleRmi');
    }
};
