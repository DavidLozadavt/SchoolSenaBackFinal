<?php

use App\Enums\EstadoHorarioMateria;
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
        Schema::create('horarioMateria', function (Blueprint $table) {
            $table->id();
            $table->time('horaInicial')->nullable();
            $table->time('horaFinal')->nullable();
            $table->enum('estado',EstadoHorarioMateria::getValues())->default(EstadoHorarioMateria::PENDIENTE);
            $table->foreignId('idGradoMateria')->references('id')->on('gradoMateria');
            $table->foreignId('idDia')->nullable()->references('id')->on('dia');
            $table->foreignId('idInfraestructura')->nullable()->references('id')->on('infraestructura');
            
            $table->foreignId('idFicha')->nullable()->references('id')->on('ficha');
            $table->date('fechaInicial')->default(now());
            $table->date('fechaFinal')->nullable();
            $table->text('observacion')->nullable();

            $table->unsignedInteger('idContrato')->nullable();
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
        Schema::dropIfExists('horarioMateria');
    }
};
