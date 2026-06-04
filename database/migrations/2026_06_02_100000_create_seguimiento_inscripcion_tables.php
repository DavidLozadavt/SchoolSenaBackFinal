<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_inscripcion', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->dateTime('token_expires_at')->nullable();
            $table->unsignedInteger('idFactura');
            $table->unsignedBigInteger('idMatricula')->nullable();
            $table->unsignedInteger('idPersona')->nullable();
            $table->unsignedBigInteger('idTercero')->nullable();
            $table->unsignedInteger('idProceso')->nullable();
            $table->unsignedInteger('idCompany');
            $table->string('estado_proceso', 40)->default('BORRADOR');
            $table->date('fecha_limite_pago')->nullable();
            $table->dateTime('fecha_correo_enviado')->nullable();
            $table->string('correo_destino', 255)->nullable();
            $table->text('observaciones_admin')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('idFactura');
            $table->index(['idCompany', 'estado_proceso']);
        });

        Schema::create('seguimiento_inscripcion_comprobante', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idSeguimientoInscripcion');
            $table->unsignedInteger('idFactura');
            $table->unsignedInteger('idPago')->nullable();
            $table->string('ruta_archivo', 500);
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('estado', 30)->default('PENDIENTE_REVISION');
            $table->text('observacion_revision')->nullable();
            $table->unsignedInteger('revisado_por')->nullable();
            $table->dateTime('fecha_carga');
            $table->dateTime('fecha_revision')->nullable();
            $table->timestamps();

            $table->index(['idSeguimientoInscripcion', 'estado'], 'seg_insc_comp_seg_estado_idx');
        });

        Schema::create('seguimiento_inscripcion_pago_gateway', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idSeguimientoInscripcion');
            $table->unsignedInteger('idFactura');
            $table->unsignedInteger('idTransaccion')->nullable();
            $table->string('gateway', 30);
            $table->string('gateway_transaction_id', 120)->nullable();
            $table->string('referencia_interna', 100)->unique();
            $table->decimal('monto', 15, 2);
            $table->string('estado_gateway', 50)->default('PENDING');
            $table->json('payload_request')->nullable();
            $table->json('payload_response')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_inscripcion_pago_gateway');
        Schema::dropIfExists('seguimiento_inscripcion_comprobante');
        Schema::dropIfExists('seguimiento_inscripcion');
    }
};
