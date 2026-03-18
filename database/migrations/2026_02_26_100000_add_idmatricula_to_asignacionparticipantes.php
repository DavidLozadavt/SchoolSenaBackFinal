<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade idMatricula a asignacionparticipantes si no existe.
     * La tabla puede haber sido creada por la migración de chat (solo idActivationCompanyUser).
     * Para grupos RAP necesitamos idMatricula -> matricula.
     */
    public function up(): void
    {
        $tableName = 'asignacionparticipantes';
        if (!Schema::hasTable($tableName)) {
            return;
        }
        if (Schema::hasColumn($tableName, 'idMatricula')) {
            return;
        }
        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('idMatricula')->nullable()->after('idGrupo');
        });
        // Añadir FK si la tabla matricula existe
        if (Schema::hasTable('matricula')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('idMatricula', 'asignacionparticipantes_idmatricula_foreign')
                    ->references('id')->on('matricula')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tableName = 'asignacionparticipantes';
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'idMatricula')) {
            return;
        }
        try {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign('asignacionparticipantes_idmatricula_foreign');
            });
        } catch (\Throwable $e) {
            // FK puede no existir
        }
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('idMatricula');
        });
    }
};
