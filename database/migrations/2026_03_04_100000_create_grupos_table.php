<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla grupos para ambiente virtual (grupos por RAP).
     * camelCase: nombreGrupo, cantidadParticipantes, idTipoGrupo, idAsignacionPeriodoProgramaJornada, idGradoMateria.
     */
    public function up(): void
    {
        if (Schema::hasTable('grupos')) {
            return;
        }

        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombreGrupo');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('INACTIVO');
            $table->text('descripcion')->nullable();
            $table->integer('cantidadParticipantes');
            $table->unsignedBigInteger('idTipoGrupo');
            $table->unsignedBigInteger('idAsignacionPeriodoProgramaJornada');
            $table->unsignedBigInteger('idGradoMateria');
            $table->timestamps();

            $table->foreign('idTipoGrupo', 'grupos_idtipogrupo_foreign')
                ->references('id')->on('tipoGrupo');
            $table->foreign('idAsignacionPeriodoProgramaJornada', 'grupos_idasignacionperiodoprogramajornada_foreign')
                ->references('id')->on('ficha');
            $table->foreign('idGradoMateria', 'grupos_idgradomateria_foreign')
                ->references('id')->on('gradoMateria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
