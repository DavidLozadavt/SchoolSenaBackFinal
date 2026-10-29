<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un aspirante inscrito (matrícula en estado INSCRIPCION) todavía no tiene
 * ficha: se asigna al matricularlo. Por eso idFicha debe aceptar NULL.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('matricula') && Schema::hasColumn('matricula', 'idFicha')) {
            DB::statement('ALTER TABLE matricula MODIFY idFicha BIGINT UNSIGNED NULL');
        }
    }

    public function down()
    {
        // No se revierte a NOT NULL: fallaría si ya hay matrículas sin ficha.
    }
};
