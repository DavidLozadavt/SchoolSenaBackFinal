<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('factura', function (Blueprint $table) {
            $table->unsignedBigInteger('idFormularioRespuesta')->nullable()->after('idTercero');
        });
    }

    public function down()
    {
        Schema::table('factura', function (Blueprint $table) {
            $table->dropColumn('idFormularioRespuesta');
        });
    }
};
