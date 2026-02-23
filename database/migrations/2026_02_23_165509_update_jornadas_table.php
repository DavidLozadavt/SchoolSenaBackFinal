<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('jornadas', function (Blueprint $table) {
            // Eliminar llave foránea existente y columna idEmpresa
            $table->dropForeign('jornadas_idempresa_foreign');
            $table->dropColumn('idEmpresa');

            // Eliminar columnas que ya no se necesitan
            $table->dropColumn(['diaSemana', 'grupoJornada']);

            // Agregar nuevas columnas
            $table->unsignedBigInteger('idCentroFormacion')->after('estado');
            $table->unsignedInteger('idCompany')->after('updated_at');

            // Agregar nueva llave foránea
            $table->foreign('idCentroFormacion', 'jornadas_centroFormacion_FK')
                ->references('id')
                ->on('centroFormacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('jornadas', function (Blueprint $table) {
            $table->dropForeign('jornadas_centroFormacion_FK');
            $table->dropColumn(['idCentroFormacion', 'idCompany']);

            $table->enum('diaSemana', [
                'Lunes',
                'Martes',
                'Miércoles',
                'Jueves',
                'Viernes',
                'Sábado',
                'Domingo'
            ])->after('nombreJornada');

            $table->integer('grupoJornada')->nullable()->after('estado');
            $table->unsignedInteger('idEmpresa')->after('grupoJornada');
            $table->foreign('idEmpresa', 'jornadas_idempresa_foreign')
                ->references('id')
                ->on('empresa')
                ->onDelete('cascade');
        });
    }
};
