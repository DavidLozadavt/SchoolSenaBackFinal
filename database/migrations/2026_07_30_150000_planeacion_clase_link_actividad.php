<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planeacion_pedagogica_clase', function (Blueprint $table) {
            if (!Schema::hasColumn('planeacion_pedagogica_clase', 'idActividad')) {
                $table->unsignedBigInteger('idActividad')->nullable()->after('tallerFin');
                $table->index('idActividad');
            }
            if (!Schema::hasColumn('planeacion_pedagogica_clase', 'idMateria')) {
                $table->unsignedBigInteger('idMateria')->nullable()->after('idActividad');
            }
            if (!Schema::hasColumn('planeacion_pedagogica_clase', 'tallerEstrategia')) {
                $table->text('tallerEstrategia')->nullable()->after('tallerContenido');
            }
            if (!Schema::hasColumn('planeacion_pedagogica_clase', 'tallerEntregables')) {
                $table->text('tallerEntregables')->nullable()->after('tallerEstrategia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planeacion_pedagogica_clase', function (Blueprint $table) {
            foreach (['tallerEntregables', 'tallerEstrategia', 'idMateria', 'idActividad'] as $col) {
                if (Schema::hasColumn('planeacion_pedagogica_clase', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
