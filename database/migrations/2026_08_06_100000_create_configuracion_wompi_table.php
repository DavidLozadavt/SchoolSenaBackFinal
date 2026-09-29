<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración global del sistema de pagos (módulo "Configuración de Pagos").
 *
 * Tabla NUEVA. No sustituye a `config/services.php`: mientras las llaves no se
 * carguen aquí, el sistema sigue tomándolas del .env exactamente igual que
 * antes. Las llaves sensibles se guardan CIFRADAS (cast `encrypted` de
 * Laravel), nunca en texto plano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracionWompi', function (Blueprint $table) {
            $table->id();
            // SANDBOX | PRODUCCION
            $table->string('modo', 20)->default('SANDBOX');
            $table->string('proveedor', 30)->default('WOMPI');
            $table->string('moneda', 10)->default('COP');
            $table->decimal('ivaPorcentaje', 5, 2)->default(0);
            $table->unsignedInteger('mensajesGratuitos')->default(10);
            $table->boolean('activo')->default(true);
            // Horas máximas para que el Administrador VT resuelva una solicitud.
            $table->unsignedInteger('horasMaxAprobacion')->default(72);
            $table->string('urlRetorno')->nullable();
            $table->string('urlWebhook')->nullable();

            // Llaves sensibles: TEXT porque el valor cifrado ocupa más que el original.
            $table->text('publicKey')->nullable();
            $table->text('privateKey')->nullable();
            $table->text('integritySecret')->nullable();
            $table->text('eventsSecret')->nullable();

            // Si es false, las llaves se siguen leyendo del .env (comportamiento actual).
            $table->boolean('usarLlavesPropias')->default(false);

            $table->unsignedBigInteger('actualizadoPor')->nullable();
            $table->timestamps();
        });

        // Fila única inicial, alineada con los valores actuales del .env.
        DB::table('configuracionWompi')->insert([
            'modo'               => 'SANDBOX',
            'proveedor'          => 'WOMPI',
            'moneda'             => (string) env('WOMPI_CURRENCY', 'COP'),
            'ivaPorcentaje'      => (float) env('WOMPI_IVA_PORCENTAJE', 0),
            'mensajesGratuitos'  => 10,
            'activo'             => true,
            'horasMaxAprobacion' => 72,
            'urlRetorno'         => env('WOMPI_REDIRECT_URL'),
            'urlWebhook'         => null,
            'usarLlavesPropias'  => false,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracionWompi');
    }
};
