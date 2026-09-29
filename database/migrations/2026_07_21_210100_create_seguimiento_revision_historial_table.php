<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial append-only del proceso de inscripción del aspirante
 * (formulario enviado, aprobado, rechazado, corrección, reenvío).
 * Nunca se actualiza una fila existente: cada evento inserta una nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_revision_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idAspirante');
            // formulario_enviado, documentacion_completa, documentacion_incompleta,
            // aprobado, rechazado, correccion_solicitada, reenviado
            $table->string('accion');
            $table->text('motivo')->nullable();
            $table->unsignedInteger('idUsuarioRevisor')->nullable(); // null si la acción es automática
            $table->dateTime('fecha');
            $table->timestamps();

            $table->foreign('idAspirante')->references('id')->on('seguimientoAspirantes')->onDelete('cascade');
            $table->foreign('idUsuarioRevisor')->references('id')->on('usuario')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_revision_historial');
    }
};
