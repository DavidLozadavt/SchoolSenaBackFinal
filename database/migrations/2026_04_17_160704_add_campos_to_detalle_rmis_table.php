<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('detalleRmi', function (Blueprint $table) {
            $table->enum('estadoInforme', ['PENDIENTE', 'ACEPTADO'])->default('PENDIENTE')->after('estadoAsociacion');
            $table->string('urlInforme', 255)->nullable()->after('estadoInforme');
            $table->string('numeroPlanilla', 50)->nullable()->after('urlInforme');
        });
    }

    public function down()
    {
        Schema::table('detalleRmi', function (Blueprint $table) {
            $table->dropColumn(['estadoInforme', 'urlInforme', 'numeroPlanilla']);
        });
    }
};