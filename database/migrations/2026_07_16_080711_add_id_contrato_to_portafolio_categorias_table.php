<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('portafolioCategorias', function (Blueprint $table) {
            $table->unsignedInteger('idContrato')->nullable()->after('idCategoriaPadre');
            $table->foreign('idContrato')->references('id')->on('contrato')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('portafolioCategorias', function (Blueprint $table) {
            $table->dropForeign(['idContrato']);
            $table->dropColumn('idContrato');
        });
    }
};