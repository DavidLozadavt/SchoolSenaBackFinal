<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarioCompartido', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idHorarioMateria');
            $table->unsignedInteger('idHorarioMateriaSecundario')->nullable();
            $table->unsignedInteger('idContratoSecundario')->nullable();
            $table->date('fechaInicial');
            $table->date('fechaFinal');
            $table->text('observacion')->nullable();
            $table->enum('estado', ['PENDIENTE', 'ACTIVO', 'FINALIZADO'])->default('PENDIENTE');
            $table->timestamps();

            $table->index(['idHorarioMateria', 'idContratoSecundario']);
            $table->index(['fechaInicial', 'fechaFinal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarioCompartido');
    }
};
