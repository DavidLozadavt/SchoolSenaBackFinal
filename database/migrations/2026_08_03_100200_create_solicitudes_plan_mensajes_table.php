<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de compra de planes de mensajes.
 *
 * Toda la información queda relacionada con `user_id` (el plan pertenece al
 * USUARIO). `company_id` se guarda solo como dato informativo para el listado del
 * Administrador VT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudesPlanMensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('companyId')->nullable();
            $table->unsignedBigInteger('planId');
            $table->string('planNombre');
            $table->unsignedInteger('cantidadMensajes');
            $table->decimal('valor', 12, 2)->default(0);
            $table->string('metodoPago');
            $table->string('comprobanteRuta')->nullable();
            $table->string('comprobanteNombre')->nullable();
            // PENDIENTE | APROBADA | RECHAZADA
            $table->string('estado')->default('PENDIENTE');
            $table->text('motivoRechazo')->nullable();
            $table->unsignedBigInteger('revisadoPor')->nullable();
            $table->dateTime('fechaRevision')->nullable();
            $table->timestamps();

            $table->index('userId');
            $table->index('estado');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudesPlanMensajes');
    }
};
