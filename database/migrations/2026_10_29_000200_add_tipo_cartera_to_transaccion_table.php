<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos y facturas académicas escriben transaccion.tipoCartera (CXC / CXP),
 * pero ninguna migración creaba la columna.
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('transaccion', 'tipoCartera')) {
            Schema::table('transaccion', function (Blueprint $table) {
                $table->string('tipoCartera', 10)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('transaccion', 'tipoCartera')) {
            Schema::table('transaccion', function (Blueprint $table) {
                $table->dropColumn('tipoCartera');
            });
        }
    }
};
