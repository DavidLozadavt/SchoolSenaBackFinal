<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial append-only de TODOS los movimientos de mensajes de un usuario.
 *
 * Tabla NUEVA y puramente de auditoría. `usuarioMensajesSaldos` (saldo actual)
 * se conserva intacta y sigue siendo la fuente de verdad del saldo: esta tabla
 * solo registra cada operación, nunca se actualiza una fila existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarioMensajesMovimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            // GRATUITO_INICIAL | RECARGA | CONSUMO | BONIFICACION | AJUSTE_MANUAL | REVERSO
            $table->string('tipoMovimiento');
            $table->integer('cantidad');
            $table->integer('saldoAnterior');
            $table->integer('saldoNuevo');
            $table->text('descripcion')->nullable();
            // Referencia libre al origen del movimiento (ej: "solicitud:12", "campania:45").
            $table->string('referencia')->nullable();
            $table->timestamps();

            $table->index('userId');
            $table->index('tipoMovimiento');
            $table->index('created_at');
        });

        // Backfill: los saldos ya creados en la Fase 1 quedan con su movimiento
        // inicial de mensajes gratuitos para que el historial cuadre.
        try {
            $ahora = now();
            DB::table('usuarioMensajesSaldos')->orderBy('id')->chunk(500, function ($saldos) use ($ahora) {
                $filas = [];
                foreach ($saldos as $saldo) {
                    $filas[] = [
                        'userId'         => $saldo->userId,
                        'tipoMovimiento' => 'GRATUITO_INICIAL',
                        'cantidad'        => $saldo->mensajesGratuitos,
                        'saldoAnterior'  => 0,
                        'saldoNuevo'     => $saldo->mensajesGratuitos,
                        'descripcion'     => 'Asignación inicial de mensajes gratuitos (registro histórico).',
                        'referencia'      => 'backfill',
                        'created_at'      => $saldo->created_at ?? $ahora,
                        'updated_at'      => $ahora,
                    ];
                }
                if ($filas) {
                    DB::table('usuarioMensajesMovimientos')->insert($filas);
                }
            });
        } catch (\Throwable $e) {
            // Si aún no hay saldos, el servicio registrará los movimientos al vuelo.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarioMensajesMovimientos');
    }
};
