<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas de idempotencia y de validación de seguridad sobre las transacciones
 * de Wompi (Mejoras 1 y 2). Columnas ADITIVAS.
 *
 *  - `procesadaEn`: sello de la única vez que la transacción generó solicitud.
 *    Mientras sea NULL, la transacción todavía no ha sido procesada.
 *  - `validacionFallida` / `motivoValidacion`: incidente detectado al validar
 *    el pago (monto distinto, moneda distinta, usuario que no corresponde...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wompiTransacciones', function (Blueprint $table) {
            if (!Schema::hasColumn('wompiTransacciones', 'procesadaEn')) {
                $table->dateTime('procesadaEn')->nullable()->after('origenActualizacion');
            }
            if (!Schema::hasColumn('wompiTransacciones', 'validacionFallida')) {
                $table->boolean('validacionFallida')->default(false)->after('procesadaEn');
            }
            if (!Schema::hasColumn('wompiTransacciones', 'motivoValidacion')) {
                $table->text('motivoValidacion')->nullable()->after('validacionFallida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wompiTransacciones', function (Blueprint $table) {
            foreach (['motivoValidacion', 'validacionFallida', 'procesadaEn'] as $columna) {
                if (Schema::hasColumn('wompiTransacciones', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
