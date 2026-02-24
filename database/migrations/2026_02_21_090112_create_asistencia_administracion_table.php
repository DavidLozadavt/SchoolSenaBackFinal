<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistenciaadministracion', function (Blueprint $table) {
            $table->id();

            $table->date('fechaLLegada');
            $table->time('horaLLegada')->nullable();
            $table->time('horaSalida')->nullable();
            $table->boolean('asistio')->default(false);

            $table->unsignedInteger('idContrato');

            $table->timestamps();

            $table->foreign('idContrato')
                  ->references('id')
                  ->on('contrato');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistenciaadministracion');
    }
};