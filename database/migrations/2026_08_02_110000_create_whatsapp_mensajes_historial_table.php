<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial append-only de cada mensaje WhatsApp enviado a un aspirante.
 * Un registro por envío (nunca se sobrescribe) — a diferencia de las
 * columnas snapshot en `seguimientoAspirantes` (estadoEnvio, waMessageId,
 * ultimo_envio) que solo guardan el ÚLTIMO envío y siguen existiendo sin
 * cambios para no romper el módulo de Seguimiento de Aspirantes.
 *
 * Se actualiza en 2 puntos, ambos aditivos:
 *  - SeguimientoAspiranteController::enviarWhatsApp → INSERT al enviar.
 *  - WhatsappWebhookController::procesarEstado → UPDATE por waMessageId
 *    cuando llega el status callback (sent/delivered/read/failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_mensajes_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seguimientoAspiranteId');
            $table->string('company_id')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('waMessageId')->nullable()->unique();
            $table->string('conversationId')->nullable();
            $table->string('template')->nullable();
            $table->string('estado')->default('sent'); // sent, delivered, read, failed
            $table->dateTime('fecha_envio');
            $table->dateTime('fecha_entregado')->nullable();
            $table->dateTime('fecha_leido')->nullable();
            $table->dateTime('fecha_error')->nullable();
            $table->text('errorDetalle')->nullable();
            $table->timestamps();

            $table->foreign('seguimientoAspiranteId')->references('id')->on('seguimientoAspirantes')->onDelete('cascade');
            $table->index('fecha_envio');
            $table->index('estado');
            $table->index('template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensajes_historial');
    }
};
