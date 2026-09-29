<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra QUIÉN importó cada aspirante, para que cada usuario vea únicamente
 * los suyos.
 *
 * Columna ADITIVA y nullable: los aspirantes que ya existían quedan en NULL
 * (sin dueño). Por decisión del negocio, esos registros históricos solo son
 * visibles para el Administrador VT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            if (!Schema::hasColumn('seguimientoAspirantes', 'importadoPorUserId')) {
                $table->unsignedBigInteger('importadoPorUserId')->nullable()->after('id');
                $table->index('importadoPorUserId');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimientoAspirantes', function (Blueprint $table) {
            if (Schema::hasColumn('seguimientoAspirantes', 'importadoPorUserId')) {
                $table->dropIndex(['importadoPorUserId']);
                $table->dropColumn('importadoPorUserId');
            }
        });
    }
};
