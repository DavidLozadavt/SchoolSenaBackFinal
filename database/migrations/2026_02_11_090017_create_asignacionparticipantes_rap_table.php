<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla asignacionparticipantes para grupos RAP (idGrupo->grupos, idMatricula->matricula).
     * NOTA: Si existe asignacionParticipantes (chat) con estructura distinta, hacer rollback de esa migración primero.
     */
    public function up(): void
    {
        $tableName = 'asignacionparticipantes';
        if (Schema::hasTable($tableName)) {
            return;
        }
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idGrupo')->nullable();
            $table->unsignedBigInteger('idMatricula');
            $table->timestamps();

            $table->foreign('idGrupo', 'asignacionparticipantes_idgrupo_foreign')
                ->references('id')->on('grupos')->nullOnDelete();
            $table->foreign('idMatricula', 'asignacionparticipantes_idmatricula_foreign')
                ->references('id')->on('matricula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacionparticipantes');
    }
};
