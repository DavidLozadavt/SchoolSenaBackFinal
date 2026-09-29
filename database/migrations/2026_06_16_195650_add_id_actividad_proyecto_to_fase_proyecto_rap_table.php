<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('faseProyectoRap', function (Blueprint $table) {
            $table->unsignedBigInteger('idActividadProyecto')->nullable()->after('idMateria');

            // Si necesitas una clave foránea:
            $table->foreign('idActividadProyecto')
                ->references('id')
                ->on('actividadesProyecto');
        });
    }

    public function down()
    {
        Schema::table('faseProyectoRap', function (Blueprint $table) {
            $table->dropForeign(['idActividadProyecto']);
            $table->dropColumn('idActividadProyecto');
        });
    }
};