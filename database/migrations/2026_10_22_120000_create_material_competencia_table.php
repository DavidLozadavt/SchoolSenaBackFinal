<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea materialCompetencia (material general por competencia).
 *
 * El remoto nunca publicó materialRap. El servidor no debe crear esa tabla.
 * Si la tabla ya existe (entorno local ya transformado), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('materialCompetencia')) {
            return;
        }

        Schema::create('materialCompetencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idCompetencia');
            $table->unsignedInteger('idPersona')->nullable();
            $table->unsignedBigInteger('idActividad')->nullable();
            $table->unsignedBigInteger('idMaterialApoyoActividad')->nullable();
            $table->string('origen', 32);
            $table->string('claveOrigen', 64)->nullable();
            $table->string('titulo', 255);
            $table->text('descripcion')->nullable();
            $table->string('tipoMaterial', 64)->nullable();
            $table->string('urlDocumento', 500)->nullable();
            $table->string('urlAdicional', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['idCompetencia', 'activo'], 'materialcompetencia_comp_activo_idx');
            $table->unique(['idCompetencia', 'claveOrigen'], 'materialcompetencia_comp_clave_unique');
            $table->index('origen', 'materialcompetencia_origen_idx');

            $table->foreign('idCompetencia', 'materialcompetencia_idcompetencia_foreign')
                ->references('id')
                ->on('materia')
                ->restrictOnDelete();
            $table->foreign('idPersona', 'materialcompetencia_idpersona_foreign')
                ->references('id')
                ->on('persona')
                ->nullOnDelete();
            $table->foreign('idActividad', 'materialcompetencia_idactividad_foreign')
                ->references('id')
                ->on('actividades')
                ->nullOnDelete();
            $table->foreign('idMaterialApoyoActividad', 'materialcompetencia_idmaterialapoyoactividad_foreign')
                ->references('id')
                ->on('materialApoyoActividad')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materialCompetencia');
    }
};
