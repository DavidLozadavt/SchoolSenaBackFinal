<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Actualiza asignacioncomentarios para coincidir con el esquema requerido:
     * - idGrupo referencia grupos (en lugar de gruposChat)
     * - Agrega idMatricula, idGrupoGeneral, leido_at
     */
    public function up(): void
    {
        $tableName = 'asignacioncomentarios';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('commentable_id')->nullable()->comment('Id del usuario');
                $table->unsignedBigInteger('idGrupo')->nullable();
                $table->unsignedBigInteger('idComentario');
                $table->unsignedBigInteger('idMatricula')->nullable()->comment('Referencia de la matricula, para el chat del usuario y el admin');
                $table->timestamps();
                $table->unsignedBigInteger('idGrupoGeneral')->nullable();
                $table->timestamp('leido_at')->nullable();

                $table->foreign('idComentario', 'asignacioncomentarios_idcomentario_foreign')->references('id')->on('comentarios')->cascadeOnDelete();
                $table->foreign('idMatricula', 'asignacioncomentarios_idmatricula_foreign')->references('id')->on('matricula')->nullOnDelete();
                if (Schema::hasTable('grupos')) {
                    $table->foreign('idGrupo', 'asignacioncomentarios_idgrupo_foreign')->references('id')->on('grupos')->nullOnDelete();
                }
                if (Schema::hasTable('grupogenerales')) {
                    $table->foreign('idGrupoGeneral', 'asignacionComentarios_grupoGenerales_FK')->references('id')->on('grupogenerales')->nullOnDelete();
                }
            });
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'idMatricula')) {
                $table->unsignedBigInteger('idMatricula')->nullable()->after('idComentario')
                    ->comment('Referencia de la matricula, para el chat del usuario y el admin');
            }
            if (!Schema::hasColumn($tableName, 'idGrupoGeneral')) {
                $table->unsignedBigInteger('idGrupoGeneral')->nullable()->after('updated_at');
            }
            if (!Schema::hasColumn($tableName, 'leido_at')) {
                $table->timestamp('leido_at')->nullable()->after('idGrupoGeneral');
            }
        });

        $fkExists = function (string $constraintName) use ($tableName): bool {
            $r = DB::select("
                SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND LOWER(TABLE_NAME) = LOWER(?)
                AND CONSTRAINT_NAME = ?
            ", [$tableName, $constraintName]);
            return !empty($r);
        };

        if (Schema::hasTable('grupogenerales') && !$fkExists('asignacionComentarios_grupoGenerales_FK')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('idGrupoGeneral', 'asignacionComentarios_grupoGenerales_FK')
                    ->references('id')->on('grupogenerales')->nullOnDelete();
            });
        }

        if (Schema::hasTable('matricula') && !$fkExists('asignacioncomentarios_idmatricula_foreign')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('idMatricula', 'asignacioncomentarios_idmatricula_foreign')
                    ->references('id')->on('matricula')->nullOnDelete();
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && Schema::hasTable('grupos')) {
            try {
                $foreigns = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND LOWER(TABLE_NAME) = LOWER(?)
                    AND COLUMN_NAME = 'idGrupo'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ", [$tableName]);

                foreach ($foreigns as $fk) {
                    DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                }
            } catch (\Throwable $e) {
                // Ignorar si no existe
            }

            if (!$fkExists('asignacioncomentarios_idgrupo_foreign')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('idGrupo', 'asignacioncomentarios_idgrupo_foreign')
                        ->references('id')->on('grupos')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $tableName = 'asignacioncomentarios';
        if (!Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            try {
                $table->dropForeign(['idGrupoGeneral']);
            } catch (\Throwable $e) {}
            try {
                $table->dropForeign(['idMatricula']);
            } catch (\Throwable $e) {}
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            try {
                DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `asignacioncomentarios_idgrupo_foreign`");
                DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `asignacioncomentarios_idgrupo_foreign` FOREIGN KEY (`idGrupo`) REFERENCES `gruposChat` (`id`) ON DELETE SET NULL");
            } catch (\Throwable $e) {}
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['idMatricula', 'idGrupoGeneral', 'leido_at']);
        });
    }
};
