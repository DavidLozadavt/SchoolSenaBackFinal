<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoGrupoSeeder extends Seeder
{
    /**
     * Inserta el tipo de grupo "General" para el módulo de grupos por RAP.
     * Ejecutar: php artisan db:seed --class=TipoGrupoSeeder
     */
    public function run(): void
    {
        $table = 'tipoGrupo';
        if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
            return;
        }
        $existe = DB::table($table)->where('nombreTipoGrupo', 'General')->exists();
        if (!$existe) {
            DB::table($table)->insert([
                'nombreTipoGrupo' => 'General',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
