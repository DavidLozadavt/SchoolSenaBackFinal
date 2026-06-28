<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('empresa_modulo_eduexce')) {
            Schema::create('empresa_modulo_eduexce', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_empresa')->unique();
                $table->unsignedBigInteger('id_institucion_eduexce')->nullable();
                $table->boolean('activo')->default(false);
                $table->date('fecha_vigencia_fin')->nullable();
                $table->timestamp('provisionado_at')->nullable();
                $table->text('ultimo_error')->nullable();
                $table->timestamps();

                $table->foreign('id_empresa')->references('id')->on('empresa')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('persona_eduexce')) {
            Schema::create('persona_eduexce', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_persona');
                $table->unsignedInteger('id_empresa');
                $table->unsignedBigInteger('id_usuario_eduexce')->nullable();
                $table->string('estado', 30)->default('pendiente');
                $table->text('ultimo_error')->nullable();
                $table->timestamp('habilitado_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();

                $table->unique(['id_persona', 'id_empresa']);
                $table->foreign('id_persona')->references('id')->on('persona')->onDelete('cascade');
                $table->foreign('id_empresa')->references('id')->on('empresa')->onDelete('cascade');
            });
        }

        if (!DB::table('permissions')->where('name', 'GESTION_ICFES')->exists()) {
            $permiso = [
                'name' => 'GESTION_ICFES',
                'guard_name' => 'web',
                'description' => 'Módulo ICFES / EduExce',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('permissions', 'icon')) {
                $permiso['icon'] = 'book-open';
            }
            if (Schema::hasColumn('permissions', 'path')) {
                $permiso['path'] = '/icfes';
            }
            if (Schema::hasColumn('permissions', 'idPermissionPadre')) {
                $permiso['idPermissionPadre'] = null;
            }
            DB::table('permissions')->insert($permiso);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_eduexce');
        Schema::dropIfExists('empresa_modulo_eduexce');
        DB::table('permissions')->where('name', 'GESTION_ICFES')->delete();
    }
};
