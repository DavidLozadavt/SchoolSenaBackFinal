<?php

use App\Support\MaterialCompetenciaRecursoSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material de Apoyo: urlVideo + tabla 1-N materialCompetenciaRecurso + copia idempotente
 * desde urlDocumento / urlVideo / urlAdicional.
 * Conserva los campos legacy. Idempotente si el esquema o los recursos ya existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materialCompetencia')) {
            return;
        }

        if (! Schema::hasColumn('materialCompetencia', 'urlVideo')) {
            Schema::table('materialCompetencia', function (Blueprint $table) {
                $table->string('urlVideo', 500)->nullable()->after('urlAdicional');
            });
        }

        if (! Schema::hasTable('materialCompetenciaRecurso')) {
            Schema::create('materialCompetenciaRecurso', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('idMaterialCompetencia');
                $table->string('tipo', 32);
                $table->string('titulo', 255)->nullable();
                $table->text('descripcion')->nullable();
                $table->string('ruta', 1000);
                $table->string('formato', 32)->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->index(['idMaterialCompetencia', 'tipo'], 'matcomprecurso_material_tipo_idx');
                $table->foreign('idMaterialCompetencia', 'matcomprecurso_idmaterial_foreign')
                    ->references('id')
                    ->on('materialCompetencia')
                    ->cascadeOnDelete();
            });
        }

        MaterialCompetenciaRecursoSync::consolidarDesdeCamposLegacy();
    }

    public function down(): void
    {
        // No elimina urlVideo ni materialCompetenciaRecurso: pueden contener
        // datos copiados o recursos creados después de esta migración.
    }
};
