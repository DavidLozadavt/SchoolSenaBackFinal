<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto ampliado de la auditoría de planes (Mejora 5).
 *
 * Columnas ADITIVAS sobre `auditoriaPlanesMensajes`. Los registros anteriores
 * quedan con estas columnas en NULL y siguen siendo válidos: nada se reescribe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoriaPlanesMensajes', function (Blueprint $table) {
            $columnas = [
                'planId'          => fn () => $table->unsignedBigInteger('planId')->nullable(),
                'planNombre'      => fn () => $table->string('planNombre')->nullable(),
                'cantidadMensajes' => fn () => $table->integer('cantidadMensajes')->nullable(),
                'referenciaPago'  => fn () => $table->string('referenciaPago')->nullable(),
                'transactionId'   => fn () => $table->string('transactionId')->nullable(),
                'estadoAnterior'  => fn () => $table->string('estadoAnterior')->nullable(),
                'estadoNuevo'     => fn () => $table->string('estadoNuevo')->nullable(),
                'fechaPago'       => fn () => $table->dateTime('fechaPago')->nullable(),
                'fechaAprobacion' => fn () => $table->dateTime('fechaAprobacion')->nullable(),
                'ip'              => fn () => $table->string('ip', 45)->nullable(),
                'userAgent'       => fn () => $table->string('userAgent', 512)->nullable(),
                'observaciones'   => fn () => $table->text('observaciones')->nullable(),
            ];

            foreach ($columnas as $nombre => $definicion) {
                if (!Schema::hasColumn('auditoriaPlanesMensajes', $nombre)) {
                    $definicion();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('auditoriaPlanesMensajes', function (Blueprint $table) {
            $columnas = [
                'observaciones', 'userAgent', 'ip', 'fechaAprobacion', 'fechaPago',
                'estadoNuevo', 'estadoAnterior', 'transactionId', 'referenciaPago',
                'cantidadMensajes', 'planNombre', 'planId',
            ];

            foreach ($columnas as $columna) {
                if (Schema::hasColumn('auditoriaPlanesMensajes', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
