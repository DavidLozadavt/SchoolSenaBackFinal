<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones in-app del módulo de Planes de Mensajes (Mejora 6).
 *
 * Tabla NUEVA e independiente del sistema de notificaciones existente. No se
 * envían correos: el usuario las consulta desde la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacionesPlanes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            // PAGO_APROBADO | SOLICITUD_ENVIADA | SOLICITUD_APROBADA | SOLICITUD_RECHAZADA
            // | PLAN_ACTIVADO | MENSAJES_ACREDITADOS | SALDO_INSUFICIENTE
            $table->string('tipo');
            $table->string('titulo');
            $table->text('mensaje');
            // success | info | warning | danger
            $table->string('nivel', 20)->default('info');
            $table->unsignedBigInteger('solicitudId')->nullable();
            $table->unsignedBigInteger('transaccionId')->nullable();
            $table->json('datos')->nullable();
            $table->dateTime('leidaEn')->nullable();
            $table->timestamps();

            $table->index('userId');
            $table->index('tipo');
            $table->index('leidaEn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacionesPlanes');
    }
};
