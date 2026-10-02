<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Alter local database table
        if (!Schema::hasColumn('empresa', 'idFormularioInscripcion')) {
            Schema::table('empresa', function (Blueprint $table) {
                $table->unsignedBigInteger('idFormularioInscripcion')->nullable();
                $table->tinyInteger('inscripcionHabilitada')->default(0);
                $table->dateTime('fechaInicioInscripcion')->nullable();
                $table->dateTime('fechaFinInscripcion')->nullable();
            });
        }

        // 2. Alter NexiService database table
        if (!Schema::connection('mysql_nexiservice')->hasColumn('empresa', 'idFormularioInscripcion')) {
            Schema::connection('mysql_nexiservice')->table('empresa', function (Blueprint $table) {
                $table->unsignedBigInteger('idFormularioInscripcion')->nullable();
                $table->tinyInteger('inscripcionHabilitada')->default(0);
                $table->dateTime('fechaInicioInscripcion')->nullable();
                $table->dateTime('fechaFinInscripcion')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['idFormularioInscripcion', 'inscripcionHabilitada', 'fechaInicioInscripcion', 'fechaFinInscripcion']);
        });

        Schema::connection('mysql_nexiservice')->table('empresa', function (Blueprint $table) {
            $table->dropColumn(['idFormularioInscripcion', 'inscripcionHabilitada', 'fechaInicioInscripcion', 'fechaFinInscripcion']);
        });
    }
};
