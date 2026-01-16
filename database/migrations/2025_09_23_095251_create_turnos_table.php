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
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->date('fechaTurno');
            $table->time('horaInicio');
            $table->time('horaFin');

            $table->foreignId('idvehiculo')
            ->constrained('vehiculos')
            ->onDelete('cascade');


            $table->foreignId('idConductor')
            ->constrained('persona')
            ->onDelete('cascade');


            $table->enum('estado', ['asignado', 'en_curso', 'finalizado', 'cancelado'])
            ->default('asignado');
            
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
        Schema::dropIfExists('turnos');
    }
};
