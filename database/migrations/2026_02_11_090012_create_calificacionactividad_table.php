<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calificacionActividad')) {
            return;
        }
        Schema::create('calificacionActividad', function (Blueprint $table) {
            $table->id();
            $table->text('archivo')->nullable();
            $table->date('fechaCreacion');
            $table->dateTime('fechaInicial');
            $table->dateTime('fechaFinal');
            $table->string('calificacionNumerica', 255)->nullable();
            $table->string('calificacionEstandart', 255)->nullable();
            $table->text('ComentarioDocente')->nullable();
            $table->text('ComentarioEstudiante')->nullable();
            $table->unsignedBigInteger('idActividad');
            $table->unsignedBigInteger('idAMartriculaAcademica');
            $table->unsignedInteger('idEstado')->nullable();
            $table->unsignedBigInteger('idGrupo')->nullable();
            $table->unsignedInteger('idPersona');
            $table->unsignedBigInteger('idCorte');
            $table->dateTime('fechaCalificacion')->nullable();
            $table->timestamps();
            $table->boolean('notificacionEnviada')->default(false);

            $table->foreign('idActividad', 'calificacionActividad_idactividad_foreign')
                ->references('id')->on('actividades');
            $table->foreign('idAMartriculaAcademica', 'calificacionActividad_idamartriculaacademica_foreign')
                ->references('id')->on('matriculaAcademica');
            $table->foreign('idEstado', 'calificacionActividad_idestado_foreign')
                ->references('id')->on('estado');
            $table->foreign('idGrupo', 'calificacionActividad_idgrupo_foreign')
                ->references('id')->on('grupos');
            $table->foreign('idPersona', 'calificacionActividad_idpersona_foreign')
                ->references('id')->on('persona');
            $table->foreign('idCorte', 'calificacionActividad_idcorte_foreign')
                ->references('id')->on('configuracionCortes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calificacionActividad');
    }
};
