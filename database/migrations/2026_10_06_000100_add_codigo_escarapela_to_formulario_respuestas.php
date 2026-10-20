<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codigo unico por inscrito para la escarapela de un evento: es lo unico que
 * lleva el QR (nunca el nombre ni la identificacion).
 */
return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('formulario_respuestas', 'codigoEscarapela')) {
            Schema::table('formulario_respuestas', function (Blueprint $table) {
                $table->string('codigoEscarapela', 40)->nullable()->unique();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('formulario_respuestas', 'codigoEscarapela')) {
            Schema::table('formulario_respuestas', function (Blueprint $table) {
                $table->dropUnique(['codigoEscarapela']);
                $table->dropColumn('codigoEscarapela');
            });
        }
    }
};
