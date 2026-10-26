<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Permission\PermissionConst;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    private const MENU_PATH = '/ambiente-virtual/actividades-competencia';

    private const MENU_TITLE = 'Actividades competencias RAP';

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
            [
                'name' => 'AULA_VIRTUAL_INSTRUCTOR',
                'guard_name' => 'web',
                'description' => 'Aula virtual para instructores - Historial RAPs, detalle de clase',
            ],
            [
                'name' => 'AULA_VIRTUAL_APRENDIZ',
                'guard_name' => 'web',
                'description' => 'Aula virtual para aprendices - Mis clases, Mis notas',
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

        $this->registrarMenuActividadesCompetenciasRap(
            PermissionConst::AULA_VIRTUAL_INSTRUCTOR_ACTIVIDADES_COMPETENCIA,
            ['INSTRUCTOR SENA'],
            PermissionConst::AULA_VIRTUAL_INSTRUCTOR
        );
        $this->registrarMenuActividadesCompetenciasRap(
            PermissionConst::AULA_VIRTUAL_APRENDIZ_ACTIVIDADES_COMPETENCIA,
            ['APRENDIZ', 'APRENDIZ SENA'],
            PermissionConst::AULA_VIRTUAL_APRENDIZ
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Ítems de menú dinámico (permissions.path + idPermissionPadre), sin migración extra.
     * El padre real del grupo es AMBIENTE_VIRTUAL (junto a Mis Formaciones / Mis Actividades).
     *
     * @param  list<string>  $rolesExtra
     */
    private function registrarMenuActividadesCompetenciasRap(string $hijoName, array $rolesExtra, string $fallbackPadreName): void
    {
        try {
            $padre = Permission::query()->where('name', 'AMBIENTE_VIRTUAL')->first()
                ?? Permission::query()->where('name', $fallbackPadreName)->first();
            $hijo = Permission::query()->firstOrCreate(
                ['name' => $hijoName, 'guard_name' => 'web'],
                [
                    'description' => self::MENU_TITLE,
                    'icon' => null,
                    'path' => self::MENU_PATH,
                    'idPermissionPadre' => $padre?->id,
                ]
            );

            $dirty = false;
            if ($padre && (int) $hijo->idPermissionPadre !== (int) $padre->id) {
                $hijo->idPermissionPadre = $padre->id;
                $dirty = true;
            }
            if ($hijo->path !== self::MENU_PATH) {
                $hijo->path = self::MENU_PATH;
                $dirty = true;
            }
            if ($hijo->description !== self::MENU_TITLE) {
                $hijo->description = self::MENU_TITLE;
                $dirty = true;
            }
            if ($dirty) {
                $hijo->save();
            }

            // No copiar todos los roles de AMBIENTE_VIRTUAL: es compartido instructor/aprendiz.
            foreach ($rolesExtra as $nombreRol) {
                $rol = Role::query()->where('name', $nombreRol)->first();
                if ($rol) {
                    $rol->givePermissionTo($hijo);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar el menú Actividades competencias RAP: '.$e->getMessage());
        }
    }
}
