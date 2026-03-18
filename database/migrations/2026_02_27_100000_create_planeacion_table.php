<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla planeacion - requerida por planeacionActividades.
     * Estructura según SQL existente con camelCase.
     */
    public function up(): void
    {
        if (Schema::hasTable('planeacion')) {
            return;
        }
        Schema::create('planeacion', function (Blueprint $table) {
            $table->id();
            $table->text('objetivos')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('conclusiones')->nullable();
            $table->text('descripcion');
            $table->text('aprendizajeEsperado')->nullable();
            $table->text('estrategia')->nullable();
            $table->date('fechaInicial');
            $table->date('fechaFinal');
            $table->unsignedInteger('idContrato');
            $table->unsignedInteger('idPlaneador')->nullable();
            $table->timestamps();

            $table->index('idContrato');
            $table->index('idPlaneador');

            $table->foreign('idContrato', 'planeacion_idcontrato_foreign')
                ->references('id')->on('contrato');
            $table->foreign('idPlaneador', 'planeacion_idplaneador_foreign')
                ->references('id')->on('planeadores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planeacion');
    }
};
