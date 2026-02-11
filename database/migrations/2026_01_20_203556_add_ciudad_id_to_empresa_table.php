<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            // 1. Columna nullable
            $table->unsignedInteger('idCiudad')->nullable()->after('digitoVerificacion');

            // 2. Llave foránea
            $table->foreign('idCiudad')
                  ->references('id')
                  ->on('ciudad')
                  ->nullOnDelete(); // si se borra la ciudad, queda NULL
        });
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropForeign(['idCiudad']);
            $table->dropColumn('idCiudad');
        });
    }
};
