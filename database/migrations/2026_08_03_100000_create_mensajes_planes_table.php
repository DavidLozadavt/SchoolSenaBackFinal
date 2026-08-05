<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de planes de mensajes de WhatsApp.
 *
 * Tabla NUEVA y autónoma: no modifica ninguna tabla existente. El plan se compra
 * por USUARIO (ver `solicitudesPlanMensajes` y `usuarioMensajesSaldos`), no por
 * empresa ni por rol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajesPlanes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedInteger('cantidadMensajes');
            $table->decimal('precio', 12, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('activo');
        });

        // Planes de ejemplo solicitados (Básico / Profesional / Empresarial).
        DB::table('mensajesPlanes')->insert([
            [
                'nombre'           => 'Básico',
                'cantidadMensajes' => 500,
                'precio'           => 50000,
                'descripcion'      => 'Plan básico con 500 mensajes de WhatsApp.',
                'activo'           => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'nombre'           => 'Profesional',
                'cantidadMensajes' => 2000,
                'precio'           => 180000,
                'descripcion'      => 'Plan profesional con 2.000 mensajes de WhatsApp.',
                'activo'           => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'nombre'           => 'Empresarial',
                'cantidadMensajes' => 10000,
                'precio'           => 800000,
                'descripcion'      => 'Plan empresarial con 10.000 mensajes de WhatsApp.',
                'activo'           => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajesPlanes');
    }
};
