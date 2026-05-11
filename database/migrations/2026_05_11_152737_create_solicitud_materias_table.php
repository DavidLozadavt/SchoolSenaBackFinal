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
        Schema::create('solicitudMateria', function (Blueprint $table) {
            $table->id();
            $table->text('observacion')->nullable(); 
            $table->unsignedBigInteger('idMateria');
            $table->unsignedBigInteger('idCategoriaFormacion');
            $table->unsignedBigInteger('idFicha');
            $table->unsignedInteger('idContrato');  
            $table->date('fechaInicio');
            $table->date('fechaFin');
            $table->timestamps();
            
            // relaciones

            $table->foreign('idMateria')
                ->references('id')->on('materia');

            $table->foreign('idCategoriaFormacion')
                ->references('id')->on('categoriaFormacion');

            $table->foreign('idFicha')
                ->references('id')->on('ficha');

            $table->foreign('idContrato')
                ->references('id')->on('contrato');

            $table->enum('estado', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO'])
                ->default('PENDIENTE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('solicitudMateria');
    }
};
