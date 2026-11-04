<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Material de Apoyo: la unidad deja de ser COMPETENCIA y pasa a COMPETENCIA + FICHA.
 *
 * idFicha queda nullable: los  registros históricos no tienen ficha demostrable
 * y no se rellenan de forma arbitraria. Los materiales nuevos sí deben llevarla.
 *
 * Clase, fecha, horarioMateria y jornada NO forman parte de esta clave.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materialCompetencia') || ! Schema::hasTable('ficha')) {
            return;
        }

        if (! Schema::hasColumn('materialCompetencia', 'idFicha')) {
            Schema::table('materialCompetencia', function (Blueprint $table) {
                $table->unsignedBigInteger('idFicha')->nullable()->after('idCompetencia');
            });
        }

        $indexes = $this->indexNames();

        Schema::table('materialCompetencia', function (Blueprint $table) use ($indexes) {
            if (in_array('materialcompetencia_comp_clave_unique', $indexes, true)) {
                $table->dropUnique('materialcompetencia_comp_clave_unique');
            }
            if (! in_array('materialcompetencia_comp_ficha_idx', $indexes, true)) {
                $table->index(['idCompetencia', 'idFicha'], 'materialcompetencia_comp_ficha_idx');
            }
            if (! in_array('materialcompetencia_comp_ficha_clave_unique', $indexes, true)) {
                $table->unique(
                    ['idCompetencia', 'idFicha', 'claveOrigen'],
                    'materialcompetencia_comp_ficha_clave_unique'
                );
            }
        });

        if (! $this->foreignExists('materialcompetencia_idficha_foreign')) {
            Schema::table('materialCompetencia', function (Blueprint $table) {
                $table->foreign('idFicha', 'materialcompetencia_idficha_foreign')
                    ->references('id')
                    ->on('ficha')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('materialCompetencia')) {
            return;
        }

        $indexes = $this->indexNames();

        if ($this->foreignExists('materialcompetencia_idficha_foreign')) {
            Schema::table('materialCompetencia', function (Blueprint $table) {
                $table->dropForeign('materialcompetencia_idficha_foreign');
            });
        }

        Schema::table('materialCompetencia', function (Blueprint $table) use ($indexes) {
            if (in_array('materialcompetencia_comp_ficha_clave_unique', $indexes, true)) {
                $table->dropUnique('materialcompetencia_comp_ficha_clave_unique');
            }
            if (in_array('materialcompetencia_comp_ficha_idx', $indexes, true)) {
                $table->dropIndex('materialcompetencia_comp_ficha_idx');
            }
            if (
                Schema::hasColumn('materialCompetencia', 'claveOrigen')
                && ! in_array('materialcompetencia_comp_clave_unique', $indexes, true)
            ) {
                $table->unique(['idCompetencia', 'claveOrigen'], 'materialcompetencia_comp_clave_unique');
            }
        });

        if (Schema::hasColumn('materialCompetencia', 'idFicha')) {
            Schema::table('materialCompetencia', function (Blueprint $table) {
                $table->dropColumn('idFicha');
            });
        }
    }

    /**
     * @return list<string>
     */
    private function indexNames(): array
    {
        try {
            return collect(DB::select('SHOW INDEX FROM materialCompetencia'))
                ->pluck('Key_name')
                ->map(fn ($name) => (string) $name)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function foreignExists(string $name): bool
    {
        try {
            $db = Schema::getConnection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT 1 AS ok FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = ?
                 LIMIT 1',
                [$db, 'materialCompetencia', $name, 'FOREIGN KEY']
            );

            return (bool) $row;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
