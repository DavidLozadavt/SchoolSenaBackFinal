<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuando materialApoyoRap ya existía antes de la migración completa,
 * el create con "if (hasTable) return" dejó una estructura incompleta en producción.
 * Esta migración sólo AGREGA columnas/índices/FK si faltan (idempotente sobre MySQL).
 */
return new class extends Migration
{
    private string $table = 'materialApoyoRap';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        if (! Schema::hasColumn($this->table, 'idFicha')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->unsignedBigInteger('idFicha')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn($this->table, 'idPersona')) {
            Schema::table($this->table, function (Blueprint $table) {
                if (Schema::hasColumn($this->table, 'idRap')) {
                    $table->unsignedBigInteger('idPersona')->nullable()->after('idRap');
                } else {
                    $table->unsignedBigInteger('idPersona')->nullable();
                }
            });
        }

        if (! Schema::hasColumn($this->table, 'urlVideo')) {
            Schema::table($this->table, function (Blueprint $table) {
                if (Schema::hasColumn($this->table, 'urlAdicional')) {
                    $table->string('urlVideo', 500)->nullable()->after('urlAdicional');
                } else {
                    $table->string('urlVideo', 500)->nullable();
                }
            });
        }

        if (! Schema::hasColumn($this->table, 'activo')) {
            Schema::table($this->table, function (Blueprint $table) {
                $after = 'urlVideo';
                if (! Schema::hasColumn($this->table, 'urlVideo') && Schema::hasColumn($this->table, 'urlAdicional')) {
                    $after = 'urlAdicional';
                } elseif (! Schema::hasColumn($this->table, 'urlVideo')) {
                    $after = 'id';
                }
                if (Schema::hasColumn($this->table, $after)) {
                    $table->boolean('activo')->default(true)->after($after);
                } else {
                    $table->boolean('activo')->default(true);
                }
            });
        }

        if (! $this->indexExists('materialapoyorap_ficha_rap_idx')) {
            try {
                Schema::table($this->table, function (Blueprint $blueprint) {
                    $blueprint->index(['idFicha', 'idRap'], 'materialapoyorap_ficha_rap_idx');
                });
            } catch (\Throwable) {
                //
            }
        }

        if (! $this->indexExists('materialapoyorap_activo_idx')) {
            try {
                Schema::table($this->table, function (Blueprint $blueprint) {
                    $blueprint->index('activo', 'materialapoyorap_activo_idx');
                });
            } catch (\Throwable) {
                //
            }
        }

        $this->addForeignKeyIfMissingSafe(
            'materialapoyorap_idficha_foreign',
            function (Blueprint $blueprint) {
                $blueprint->foreign('idFicha', 'materialapoyorap_idficha_foreign')
                    ->references('id')
                    ->on('ficha')
                    ->cascadeOnDelete();
            },
            fn () => Schema::hasColumn($this->table, 'idFicha')
        );

        $this->addForeignKeyIfMissingSafe(
            'materialapoyorap_idmateria_foreign',
            function (Blueprint $blueprint) {
                $blueprint->foreign('idMateria', 'materialapoyorap_idmateria_foreign')
                    ->references('id')
                    ->on('materia')
                    ->restrictOnDelete();
            },
            fn () => Schema::hasColumn($this->table, 'idMateria')
        );

        $this->addForeignKeyIfMissingSafe(
            'materialapoyorap_idrap_foreign',
            function (Blueprint $blueprint) {
                $blueprint->foreign('idRap', 'materialapoyorap_idrap_foreign')
                    ->references('id')
                    ->on('materia')
                    ->restrictOnDelete();
            },
            fn () => Schema::hasColumn($this->table, 'idRap')
        );

        $this->addForeignKeyIfMissingSafe(
            'materialapoyorap_idpersona_foreign',
            function (Blueprint $blueprint) {
                $blueprint->foreign('idPersona', 'materialapoyorap_idpersona_foreign')
                    ->references('id')
                    ->on('persona')
                    ->nullOnDelete();
            },
            fn () => Schema::hasColumn($this->table, 'idPersona')
        );
    }

    /**
     * Revertir sólo lo que suele crearse cuando la tabla ya existía en versión incompleta.
     * Ejecutar con cuidado si ya hay FK con otros nombres.
     */
    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        $this->dropForeignKeyIfExists('materialapoyorap_idpersona_foreign');
        $this->dropForeignKeyIfExists('materialapoyorap_idrap_foreign');
        $this->dropForeignKeyIfExists('materialapoyorap_idmateria_foreign');
        $this->dropForeignKeyIfExists('materialapoyorap_idficha_foreign');

        Schema::table($this->table, function (Blueprint $blueprint) {
            if ($this->indexExists('materialapoyorap_activo_idx')) {
                $blueprint->dropIndex('materialapoyorap_activo_idx');
            }
            if ($this->indexExists('materialapoyorap_ficha_rap_idx')) {
                $blueprint->dropIndex('materialapoyorap_ficha_rap_idx');
            }
        });

        Schema::table($this->table, function (Blueprint $blueprint) {
            if (Schema::hasColumn($this->table, 'activo')) {
                $blueprint->dropColumn('activo');
            }
            if (Schema::hasColumn($this->table, 'urlVideo')) {
                $blueprint->dropColumn('urlVideo');
            }
            if (Schema::hasColumn($this->table, 'idPersona')) {
                $blueprint->dropColumn('idPersona');
            }
            if (Schema::hasColumn($this->table, 'idFicha')) {
                $blueprint->dropColumn('idFicha');
            }
        });
    }

    private function indexExists(string $keyName): bool
    {
        try {
            $rows = DB::select('SHOW INDEX FROM `'.$this->table.'`');
        } catch (\Throwable) {
            return false;
        }
        foreach ($rows as $row) {
            if (($row->Key_name ?? '') === $keyName) {
                return true;
            }
        }

        return false;
    }

    private function fkExists(string $constraintName): bool
    {
        $db = DB::getDatabaseName();
        $n = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('TABLE_NAME', $this->table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->count();

        return $n > 0;
    }

    private function addForeignKeyIfMissingSafe(string $constraintName, \Closure $callback, ?\Closure $columnOk = null): void
    {
        if ($this->fkExists($constraintName)) {
            return;
        }
        if ($columnOk !== null && ! $columnOk()) {
            return;
        }
        try {
            Schema::table($this->table, function (Blueprint $blueprint) use ($callback) {
                $callback($blueprint);
            });
        } catch (\Throwable) {
            // Ya existe FK con otro nombre o error de motor; revisar información_schema si hace falta.
        }
    }

    private function dropForeignKeyIfExists(string $constraintName): void
    {
        if ($this->fkExists($constraintName)) {
            Schema::table($this->table, function (Blueprint $blueprint) use ($constraintName) {
                $blueprint->dropForeign($constraintName);
            });
        }
    }
};
