<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar llaves foráneas y columnas viejas si existen
        if (Schema::hasColumn('jornadas', 'idEmpresa')) {
            Schema::table('jornadas', function (Blueprint $table) {
                $table->dropForeign('jornadas_idempresa_foreign');
                $table->dropColumn('idEmpresa');
            });
        }

        // Eliminar columnas que ya no se necesitan
        $columnsToDrop = ['diaSemana', 'grupoJornada', 'idCompany'];
        foreach ($columnsToDrop as $col) {
            if (Schema::hasColumn('jornadas', $col)) {
                Schema::table('jornadas', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        // Agregar idCentroFormacion 
        Schema::table('jornadas', function (Blueprint $table) {
            // SE DEJA COMO NULLABLE PARA NO ROMPER LA BASE DE DATOS Y PODER CREAR LA FK, 
            //ACTIVAR MANUALMENTE DESPUES DE EJECUTAR LA MIGRACION Y ASIGNAR EL VALOR A LOS REGISTROS EXISTENTES
            $table->unsignedBigInteger('idCentroFormacion')->nullable()->after('estado');
        });

        // Agregar llave foránea
        Schema::table('jornadas', function (Blueprint $table) {
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
        //
    }
};
