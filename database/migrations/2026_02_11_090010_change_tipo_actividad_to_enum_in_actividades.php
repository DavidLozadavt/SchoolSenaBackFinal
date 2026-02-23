<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropForeign('actividades_idtipoactividad_foreign');
        });
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('idTipoActividad');
        });
        Schema::table('actividades', function (Blueprint $table) {
            $table->enum('tipoActividad', ['sin evidencia', 'con evidencia', 'cuestionario'])
                ->default('sin evidencia')
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn('tipoActividad');
        });
        Schema::table('actividades', function (Blueprint $table) {
            $table->unsignedInteger('idTipoActividad')->after('id');
            $table->foreign('idTipoActividad', 'actividades_idtipoactividad_foreign')
                ->references('id')->on('tipo_actividades');
        });
    }
};
