<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registra participantes asignados a actividades.
     */
    public function up(): void
    {
        $tableName = 'asignacionParticipantes';
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
        Schema::dropIfExists('asignacionParticipantes');
    }
};
