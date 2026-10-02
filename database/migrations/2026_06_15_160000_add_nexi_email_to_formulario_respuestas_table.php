<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNexiEmailToFormularioRespuestasTable extends Migration
{
    public function up()
    {
        Schema::table('formulario_respuestas', function (Blueprint $table) {
            $table->string('nexiEmail')->nullable()->after('ipAddress');
        });
    }

    public function down()
    {
        Schema::table('formulario_respuestas', function (Blueprint $table) {
            $table->dropColumn('nexiEmail');
        });
    }
}
