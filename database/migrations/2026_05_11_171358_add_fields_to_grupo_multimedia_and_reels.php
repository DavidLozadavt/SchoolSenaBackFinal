<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Completar tabla grupo_multimedia
        Schema::table('grupo_multimedia', function (Blueprint $table) {
            if (!Schema::hasColumn('grupo_multimedia', 'nombreGrupo')) {
                $table->string('nombreGrupo')->nullable();
            }
            if (!Schema::hasColumn('grupo_multimedia', 'tipo')) {
                $table->enum('tipo', ['historia', 'reel'])->default('historia');
            }
            if (!Schema::hasColumn('grupo_multimedia', 'descripcion')) {
                $table->text('descripcion')->nullable();
            }
            if (!Schema::hasColumn('grupo_multimedia', 'idCompany')) {
                $table->unsignedBigInteger('idCompany')->nullable();
            }
            if (!Schema::hasColumn('grupo_multimedia', 'idUser')) {
                $table->unsignedBigInteger('idUser')->nullable();
            }
        });

        // Completar tabla multimedia_historias
        Schema::table('multimedia_historias', function (Blueprint $table) {
            if (!Schema::hasColumn('multimedia_historias', 'idGrupoMultimedia')) {
                $table->unsignedBigInteger('idGrupoMultimedia')->nullable();
            }
            if (!Schema::hasColumn('multimedia_historias', 'idCompany')) {
                $table->unsignedBigInteger('idCompany')->nullable();
            }
            if (!Schema::hasColumn('multimedia_historias', 'idUser')) {
                $table->unsignedBigInteger('idUser')->nullable();
            }
            if (!Schema::hasColumn('multimedia_historias', 'urlMultimedia')) {
                $table->string('urlMultimedia', 500)->nullable();
            }
            if (!Schema::hasColumn('multimedia_historias', 'cancion')) {
                $table->text('cancion')->nullable();
            }
            if (!Schema::hasColumn('multimedia_historias', 'descripcion')) {
                $table->text('descripcion')->nullable();
            }
            if (!Schema::hasColumn('multimedia_historias', 'tipo')) {
                $table->enum('tipo', ['historia', 'reel'])->default('historia');
            }
        });
    }

    public function down()
    {
        Schema::table('grupo_multimedia', function (Blueprint $table) {
            $cols = ['nombreGrupo', 'tipo', 'descripcion', 'idCompany', 'idUser'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('grupo_multimedia', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });

        Schema::table('multimedia_historias', function (Blueprint $table) {
            $cols = ['idGrupoMultimedia', 'idCompany', 'idUser', 'urlMultimedia', 'cancion', 'descripcion', 'tipo'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('multimedia_historias', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
