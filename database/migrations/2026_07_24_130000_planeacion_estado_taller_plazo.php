<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estado incompleta + motivo
        DB::statement("ALTER TABLE planeacion_pedagogica MODIFY COLUMN estado ENUM('BORRADOR','ENVIADA','APROBADA','EN_EJECUCION','CERRADA','INCOMPLETA') NOT NULL DEFAULT 'BORRADOR'");

        Schema::table('planeacion_pedagogica', function (Blueprint $table) {
            if (!Schema::hasColumn('planeacion_pedagogica', 'motivoIncompleta')) {
                $table->text('motivoIncompleta')->nullable()->after('reflexionDocente');
            }
        });

        Schema::table('planeacion_pedagogica_clase', function (Blueprint $table) {
            if (!Schema::hasColumn('planeacion_pedagogica_clase', 'tallerInicio')) {
                $table->dateTime('tallerInicio')->nullable()->after('tallerContenido');
            }
            if (!Schema::hasColumn('planeacion_pedagogica_clase', 'tallerFin')) {
                $table->dateTime('tallerFin')->nullable()->after('tallerInicio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planeacion_pedagogica_clase', function (Blueprint $table) {
            if (Schema::hasColumn('planeacion_pedagogica_clase', 'tallerFin')) {
                $table->dropColumn('tallerFin');
            }
            if (Schema::hasColumn('planeacion_pedagogica_clase', 'tallerInicio')) {
                $table->dropColumn('tallerInicio');
            }
        });

        Schema::table('planeacion_pedagogica', function (Blueprint $table) {
            if (Schema::hasColumn('planeacion_pedagogica', 'motivoIncompleta')) {
                $table->dropColumn('motivoIncompleta');
            }
        });

        DB::statement("ALTER TABLE planeacion_pedagogica MODIFY COLUMN estado ENUM('BORRADOR','ENVIADA','APROBADA','EN_EJECUCION','CERRADA') NOT NULL DEFAULT 'BORRADOR'");
    }
};
