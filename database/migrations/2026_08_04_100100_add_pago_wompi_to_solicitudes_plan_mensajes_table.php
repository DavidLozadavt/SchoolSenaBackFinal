<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas ADITIVAS en `solicitudesPlanMensajes` para el pago por Wompi.
 *
 * Justificación del cambio sobre una tabla existente: la pantalla de
 * Solicitudes de Planes del Administrador VT debe mostrar el estado del pago,
 * la referencia y el transaction_id junto a cada solicitud. Se limita a añadir
 * columnas nullable: ninguna columna existente se modifica ni se elimina, y
 * las solicitudes creadas con comprobante manual siguen funcionando igual
 * (estas columnas quedan en NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudesPlanMensajes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudesPlanMensajes', 'wompiTransaccionId')) {
                $table->unsignedBigInteger('wompiTransaccionId')->nullable()->after('comprobanteNombre');
            }
            if (!Schema::hasColumn('solicitudesPlanMensajes', 'estadoPago')) {
                // NULL = flujo manual con comprobante. PENDING/APPROVED/DECLINED/VOIDED/ERROR = Wompi.
                $table->string('estadoPago')->nullable()->after('wompiTransaccionId');
            }
            if (!Schema::hasColumn('solicitudesPlanMensajes', 'referenciaPago')) {
                $table->string('referenciaPago')->nullable()->after('estadoPago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudesPlanMensajes', function (Blueprint $table) {
            foreach (['referenciaPago', 'estadoPago', 'wompiTransaccionId'] as $columna) {
                if (Schema::hasColumn('solicitudesPlanMensajes', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
