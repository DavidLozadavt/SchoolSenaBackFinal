<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('ficha', function (Blueprint $table) {
            // Elimina la llave foránea (usa el nombre de la restricción o el array con la columna)
            $table->dropForeign('ficha_idjornada_foreign');

            // Elimina la columna
            $table->dropColumn('idJornada');
        });
    }

    public function down()
    {
        Schema::table('ficha', function (Blueprint $table) {
            // Reverte: Agrega la columna y la llave foránea de nuevo
            $table->unsignedBigInteger('idJornada')->nullable()->after('idSede'); // Ajusta la posición según tu esquema
            $table->foreign('idJornada', 'ficha_idjornada_foreign')
                ->references('id')
                ->on('jornadas');
        });
    }
};