<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo de mensajes de WhatsApp POR USUARIO.
 *
 * Se crea como tabla satélite (1-1 con `usuario`) en lugar de agregar columnas a
 * la tabla `usuario`, para no tocar el modelo de usuarios existente. Cada usuario
 * arranca con 10 mensajes gratuitos.
 *
 * La fila se crea de forma perezosa (firstOrCreate) desde SaldoMensajesService, por
 * lo que los usuarios nuevos reciben automáticamente sus 10 mensajes gratuitos sin
 * modificar el flujo de creación de usuarios.
 */
return new class extends Migration
{
    public const MENSAJES_GRATUITOS = 10;

    public function up(): void
    {
        Schema::create('usuarioMensajesSaldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId')->unique();
            $table->unsignedInteger('mensajesGratuitos')->default(self::MENSAJES_GRATUITOS);
            $table->unsignedInteger('mensajesDisponibles')->default(self::MENSAJES_GRATUITOS);
            $table->unsignedInteger('mensajesConsumidos')->default(0);
            $table->unsignedBigInteger('planActivoId')->nullable();
            $table->dateTime('fechaActivacionPlan')->nullable();
            $table->timestamps();

            $table->index('planActivoId');
        });

        // Backfill: los usuarios ya existentes también reciben sus 10 gratuitos.
        try {
            $ahora = now();
            DB::table('usuario')->orderBy('id')->select('id')->chunk(500, function ($usuarios) use ($ahora) {
                $filas = [];
                foreach ($usuarios as $usuario) {
                    $filas[] = [
                        'userId'             => $usuario->id,
                        'mensajesGratuitos'   => self::MENSAJES_GRATUITOS,
                        'mensajesDisponibles' => self::MENSAJES_GRATUITOS,
                        'mensajesConsumidos'  => 0,
                        'created_at'          => $ahora,
                        'updated_at'          => $ahora,
                    ];
                }
                if ($filas) {
                    DB::table('usuarioMensajesSaldos')->insert($filas);
                }
            });
        } catch (\Throwable $e) {
            // Si la tabla `usuario` no existe todavía, el servicio creará las filas
            // de forma perezosa en el primer acceso.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarioMensajesSaldos');
    }
};
