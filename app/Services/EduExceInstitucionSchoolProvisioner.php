<?php

namespace App\Services;

use App\Models\ActivationCompanyUser;
use App\Models\Company;
use App\Models\EmpresaModuloEduexce;
use App\Models\Rol;
use App\Models\User;
use App\Permission\PermissionConst;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class EduExceInstitucionSchoolProvisioner
{
    public function __construct(private EduExceApiService $eduexceApi)
    {
    }

    /**
     * Crea o vincula tenant School + usuario admin con MODULO_ICFES para una institución EduExce nativa.
     */
    public function provisionarAccesoSchool(int $idInstitucionEduexce): array
    {
        $inst = $this->eduexceApi->obtenerInstitucionEduexce($idInstitucionEduexce);

        if (($inst['origen_cuenta'] ?? '') === 'school' && !empty($inst['external_school_id'])) {
            return [
                'ya_vinculada' => true,
                'id_empresa' => (int) $inst['external_school_id'],
                'email_admin' => $inst['correo'] ?? null,
            ];
        }

        return DB::transaction(function () use ($idInstitucionEduexce, $inst) {
            $correo = strtolower(trim((string) ($inst['correo'] ?? '')));
            if ($correo === '') {
                throw new \RuntimeException('La institución EduExce no tiene correo de administrador');
            }

            $nombre = (string) ($inst['nombre_institucion'] ?? 'Institución EduExce');
            $codigoDane = (string) ($inst['codigo_dane'] ?? "EDX-{$idInstitucionEduexce}");

            $modulo = EmpresaModuloEduexce::where('id_institucion_eduexce', $idInstitucionEduexce)->first();
            $company = $modulo ? Company::find($modulo->id_empresa) : null;

            if (!$company && !empty($inst['external_school_id'])) {
                $company = Company::find((int) $inst['external_school_id']);
            }

            if (!$company) {
                $company = Company::where('email', $correo)->first()
                    ?? Company::where('nit', $codigoDane)->first();
            }

            if (!$company) {
                $company = Company::create([
                    'razonSocial' => $nombre,
                    'email' => $correo,
                    'nit' => $codigoDane,
                    'representanteLegal' => $nombre,
                    'direccion' => $inst['direccion'] ?? 'Sin especificar',
                    'rutaLogo' => Company::RUTA_LOGO_DEFAULT,
                    'digitoVerificacion' => '0',
                ]);
            } else {
                $company->razonSocial = $nombre;
                $company->email = $correo;
                if (!$company->nit) {
                    $company->nit = $codigoDane;
                }
                $company->save();
            }

            $modulo = EmpresaModuloEduexce::updateOrCreate(
                ['id_empresa' => $company->id],
                [
                    'id_institucion_eduexce' => $idInstitucionEduexce,
                    'activo' => true,
                    'fecha_vigencia_fin' => now()->addYear()->toDateString(),
                    'provisionado_at' => now(),
                    'ultimo_error' => null,
                ]
            );

            $role = $this->ensureAdminInstitucionRole();
            $user = User::where('email', $correo)->first();
            $passwordHash = $inst['password_hash'] ?? null;
            $userCreado = false;

            if (!$user) {
                $user = new User();
                $user->email = $correo;
                $userCreado = true;
            }

            if ($passwordHash && str_starts_with((string) $passwordHash, '$2')) {
                // Misma clave que EduExce (registro nativo); actualiza también si el usuario ya existía
                $user->contrasena = (string) $passwordHash;
            } elseif ($userCreado) {
                $user->contrasena = bcrypt('EduExceSchool2026!');
            }

            $user->save();

            $activation = ActivationCompanyUser::where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->first();

            if (!$activation) {
                $activation = new ActivationCompanyUser();
                $activation->user_id = $user->id;
                $activation->company_id = $company->id;
                $activation->state_id = 1;
                $activation->fechaInicio = now()->toDateString();
                $activation->fechaFin = '2040-01-15';
                $activation->save();
            }

            if (!$activation->hasRole($role)) {
                $activation->assignRole($role);
            }

            $this->eduexceApi->vincularInstitucionSchool($idInstitucionEduexce, $company->id);

            Log::info('Acceso School provisionado para institución EduExce', [
                'id_institucion_eduexce' => $idInstitucionEduexce,
                'id_empresa' => $company->id,
                'email' => $correo,
            ]);

            return [
                'id_empresa' => $company->id,
                'id_institucion_eduexce' => $idInstitucionEduexce,
                'email_admin' => $correo,
                'rol' => PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE,
                'permiso' => PermissionConst::MODULO_ICFES,
                'usuario_creado' => $userCreado,
                'fecha_vigencia_fin' => $modulo->fecha_vigencia_fin,
            ];
        });
    }

    private function ensureAdminInstitucionRole(): Rol
    {
        $role = Rol::firstOrCreate(
            [
                'name' => PermissionConst::ROL_ADMIN_INSTITUCION_EDUEXCE,
                'guard_name' => 'web',
            ]
        );

        $permiso = Permission::findByName(PermissionConst::MODULO_ICFES, 'web');
        if (!$role->hasPermissionTo($permiso)) {
            $role->givePermissionTo($permiso);
        }

        return $role;
    }
}
