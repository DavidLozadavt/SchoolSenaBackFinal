<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría append-only de todo lo que ocurre con los planes/saldos de mensajes:
 * solicitud creada, aprobada, rechazada, consumo de mensajes en un envío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoriaPlanesMensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('solicitudId')->nullable();
            // SOLICITUD_CREADA | SOLICITUD_APROBADA | SOLICITUD_RECHAZADA | MENSAJES_CONSUMIDOS
            $table->string('accion');
            $table->text('descripcion')->nullable();
            $table->integer('mensajesAntes')->nullable();
            $table->integer('mensajesDespues')->nullable();
            $table->unsignedBigInteger('realizadoPor')->nullable();
            $table->json('detalle')->nullable();
            $table->timestamps();

            $table->index('userId');
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoriaPlanesMensajes');
    }
};
