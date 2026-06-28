<?php

use App\Permission\PermissionConst;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'description')) {
            return;
        }

        DB::table('permissions')
            ->where('name', PermissionConst::MODULO_ICFES)
            ->update([
                'description' => 'Conectar ICFES',
                'icon' => 'exit-right',
                'updated_at' => now(),
            ]);

        if (Schema::hasColumn('permissions', 'path')) {
            DB::table('permissions')
                ->where('name', PermissionConst::GESTION_ICFES)
                ->update([
                    'description' => '(Legacy) Conectar ICFES',
                    'path' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('permissions', 'description')) {
            return;
        }

        DB::table('permissions')
            ->where('name', PermissionConst::MODULO_ICFES)
            ->update([
                'description' => 'Módulo ICFES / EduExce (institución)',
                'icon' => 'book-open',
                'updated_at' => now(),
            ]);
    }
};
