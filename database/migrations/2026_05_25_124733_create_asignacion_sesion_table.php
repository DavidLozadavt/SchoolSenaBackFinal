<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('reemplazo', 'idHorarioMateria')) {
                $table->unsignedInteger('idHorarioMateria')->nullable()->after('id');
                $table->index(['idHorarioMateria', 'idContratoRemplazo'], 'reemplazo_horario_contrato_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reemplazo', function (Blueprint $table) {
            if (Schema::hasColumn('reemplazo', 'idHorarioMateria')) {
                $table->dropColumn('idHorarioMateria');
            }
        });
    }
};
