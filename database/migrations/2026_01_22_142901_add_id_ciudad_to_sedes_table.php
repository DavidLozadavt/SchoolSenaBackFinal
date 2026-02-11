<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sedes', function (Blueprint $table) {
            // 1. Columna nullable
            $table->unsignedInteger('idCiudad')
                ->nullable()
                ->after('idEmpresa');

            // 2. Llave foránea
            $table->foreign('idCiudad')
                ->references('id')
                ->on('ciudad')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropForeign(['idCiudad']);
            $table->dropColumn('idCiudad');
        });
    }
};
