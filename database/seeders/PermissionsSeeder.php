<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'GESTION_APRENDIZ',
                'guard_name' => 'web',
                'description' => 'Gestión de aprendices',
            ],
            [
                'name' => 'GESTION_REGIONAL',
                'guard_name' => 'web',
                'description' => 'Gestion de regionales',
            ],
            [
                'name' => 'GESTION_CENTROS_FORMACION',
                'guard_name' => 'web',
                'description' => 'Gestión de los centros de formacion para las regionales',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ],
                [
                    'description' => $permission['description'],
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ]
            );
        }
    }
}
