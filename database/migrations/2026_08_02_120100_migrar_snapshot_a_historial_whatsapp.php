<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migración de datos (no de esquema): copia el snapshot existente en
 * `seguimientoAspirantes` (último estado por aspirante) hacia el historial
 * `whatsapp_mensajes_historial`, para no perder esa información al pasar
 * a un modelo de historial completo.
 *
 * Cada fila copiada queda marcada `esMigrado = true` porque representa
 * SOLO el último estado conocido, no el histórico real de reintentos. A
 * partir de este punto, todo envío nuevo (enviarWhatsApp) y su actualización
 * de estado (webhook) se registran como eventos reales independientes.
 *
 * Idempotente: usa NOT EXISTS sobre seguimientoAspiranteId ya migrado, así
 * que correrla más de una vez no duplica filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $filas = DB::table('seguimientoAspirantes')
            ->whereNotNull('estadoEnvio')
            ->get(['id', 'waMessageId', 'ultimo_envio', 'ultimaPlantilla', 'estadoEnvio', 'errorEnvio', 'created_at']);

        $insertados = 0;

        foreach ($filas as $fila) {
            $yaMigrado = DB::table('whatsapp_mensajes_historial')
                ->where('seguimientoAspiranteId', $fila->id)
                ->where('esMigrado', true)
                ->exists();

            if ($yaMigrado) {
                continue;
            }

            $fechaBase = $fila->ultimo_envio ?? $fila->created_at ?? now();

            // insertOrIgnore: si el waMessageId ya existe (evento real ya
            // capturado en tiempo real), no duplica ni rompe la migración.
            DB::table('whatsapp_mensajes_historial')->insertOrIgnore([
                'seguimientoAspiranteId' => $fila->id,
                'waMessageId'            => $fila->waMessageId,
                'template'               => $fila->ultimaPlantilla,
                'estado'                 => $fila->estadoEnvio,
                'fecha_envio'            => $fechaBase,
                'fecha_entregado'        => in_array($fila->estadoEnvio, ['delivered', 'read'], true) ? $fechaBase : null,
                'fecha_leido'            => $fila->estadoEnvio === 'read' ? $fechaBase : null,
                'fecha_error'            => $fila->estadoEnvio === 'failed' ? $fechaBase : null,
                'errorDetalle'           => $fila->errorEnvio,
                'esMigrado'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            $insertados++;
        }

        Log::info("Migración historial WhatsApp: {$insertados} registros copiados desde seguimientoAspirantes.");
    }

    public function down(): void
    {
        DB::table('whatsapp_mensajes_historial')->where('esMigrado', true)->delete();
    }
};
