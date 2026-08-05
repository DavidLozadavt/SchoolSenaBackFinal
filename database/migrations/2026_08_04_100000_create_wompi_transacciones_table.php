<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transacciones de la pasarela de pagos Wompi para la compra de planes de mensajes.
 *
 * Tabla NUEVA y append-only en la práctica: solo se actualiza el estado que
 * reporta Wompi (consulta directa o webhook). Nunca se borran registros.
 * No modifica el sistema de saldo ni la lógica de aprobación existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wompiTransacciones', function (Blueprint $table) {
            $table->id();
            // Referencia única generada por el sistema y enviada al checkout.
            $table->string('reference')->unique();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('companyId')->nullable();
            $table->unsignedBigInteger('planId');
            $table->unsignedBigInteger('solicitudId')->nullable();

            // Datos devueltos por Wompi.
            $table->string('transactionId')->nullable()->index();
            $table->string('paymentMethod')->nullable();
            $table->string('paymentMethodType')->nullable();
            $table->unsignedBigInteger('amountInCents');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('COP');
            // PENDING | APPROVED | DECLINED | VOIDED | ERROR
            $table->string('status')->default('PENDING');
            $table->string('statusMessage')->nullable();
            $table->string('customerEmail')->nullable();
            $table->dateTime('fechaPago')->nullable();
            $table->json('respuestaWompi')->nullable();
            // Origen de la última actualización: CHECKOUT | CONSULTA | WEBHOOK
            $table->string('origenActualizacion')->nullable();
            $table->timestamps();

            $table->index('userId');
            $table->index('status');
            $table->index('solicitudId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wompiTransacciones');
    }
};
