<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('aperturarprograma', function (Blueprint $table) {
            // Agrega la columna idJornada (nullable por defecto)
            $table->unsignedBigInteger('idJornada')->nullable()->after('idSede');

            // Agrega la llave foránea
            $table->foreign('idJornada')
                ->references('id')
                ->on('jornadas');
        });
    }

    public function down()
    {
        Schema::table('aperturarprograma', function (Blueprint $table) {
            // Elimina la llave foránea primero
            $table->dropForeign(['idJornada']);
            // Luego elimina la columna
            $table->dropColumn('idJornada');
        });
    }
};