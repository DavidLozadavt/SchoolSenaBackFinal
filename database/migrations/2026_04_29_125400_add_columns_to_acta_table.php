<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('acta', function (Blueprint $table) {
            $table->string('nombre')->after('id');
            $table->time('horaInicio')->after('nombre');
            $table->time('horaFin')->after('horaInicio');
            $table->text('direccion')->after('horaFin');
        });
    }

    public function down()
    {
        Schema::table('acta', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'horaInicio', 'horaFin', 'direccion']);
        });
    }
};