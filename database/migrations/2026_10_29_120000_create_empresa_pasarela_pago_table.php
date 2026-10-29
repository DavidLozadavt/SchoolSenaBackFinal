<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Llaves de Wompi de cada institución. Las registra la propia empresa desde
 * Configuración de pagos; los secretos se guardan cifrados (cast encrypted).
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('empresaPasarelaPago')) {
            return;
        }

        Schema::create('empresaPasarelaPago', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idEmpresa')->unique();
            $table->string('proveedor', 20)->default('WOMPI');
            $table->string('publicKey')->nullable();
            $table->text('privateKey')->nullable();
            $table->text('integritySecret')->nullable();
            $table->text('eventsSecret')->nullable();
            $table->string('environment', 10)->default('test');
            $table->boolean('habilitarPSE')->default(true);
            $table->boolean('habilitarTarjetas')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('idEmpresa')->references('id')->on('empresa')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('empresaPasarelaPago');
    }
};
