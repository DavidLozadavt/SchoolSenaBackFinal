<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ficha') && !Schema::hasColumn('ficha', 'clase')) {
            Schema::table('ficha', function (Blueprint $table) {
                $table->string('clase', 10)->default('FICHA');
            });
        }

        if (Schema::hasTable('ficha') && Schema::hasColumn('ficha', 'clase') && Schema::hasTable('grado')) {
            DB::statement("
                UPDATE ficha
                INNER JOIN grado ON ficha.idGrado = grado.id AND ficha.codigo = grado.nombreGrado
                SET ficha.clase = 'GRADO'
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ficha') && Schema::hasColumn('ficha', 'clase')) {
            Schema::table('ficha', function (Blueprint $table) {
                $table->dropColumn('clase');
            });
        }
    }
};
