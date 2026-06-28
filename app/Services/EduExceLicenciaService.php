<?php

namespace App\Services;

use App\Models\EmpresaModuloEduexce;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class EduExceLicenciaService
{
    public function __construct(
        private EduExceApiService $eduexceApi,
        private EduExceInstitucionSchoolProvisioner $schoolProvisioner
    ) {
    }

    public function getOrCreateModulo(int $companyId): EmpresaModuloEduexce
    {
        return EmpresaModuloEduexce::firstOrCreate(
            ['id_empresa' => $companyId],
            [
                'activo' => false,
                'fecha_vigencia_fin' => null,
            ]
        );
    }

    /**
     * @return array{licencia_activa: bool, licencia_rechazada: bool, external_school_id: int, fecha_vigencia_fin: ?\Carbon\Carbon, id_institucion_eduexce: ?int, provisionado_at: ?\Carbon\Carbon, ultimo_error: ?string}
     */
    public function licenciaOperativa(int $companyId): array
    {
        $modulo = $this->getOrCreateModulo($companyId);

        $rechazada = $modulo->id_institucion_eduexce !== null && !$modulo->activo;

        return [
            'licencia_activa' => $modulo->licenciaVigente(),
            'licencia_rechazada' => $rechazada,
            'external_school_id' => $companyId,
            'fecha_vigencia_fin' => $modulo->fecha_vigencia_fin,
            'id_institucion_eduexce' => $modulo->id_institucion_eduexce,
            'provisionado_at' => $modulo->provisionado_at,
            'ultimo_error' => $modulo->ultimo_error,
        ];
    }

    public function activarLicenciaEduexce(int $idInstitucion): array
    {
        $eduexce = $this->eduexceApi->reactivarInstitucionEduexce($idInstitucion);
        $school = $this->schoolProvisioner->provisionarAccesoSchool($idInstitucion);

        $this->syncAdminPasswordFromEduexce($idInstitucion);

        if (!empty($school['id_empresa'])) {
            $modulo = $this->getOrCreateModulo((int) $school['id_empresa']);
            $modulo->id_institucion_eduexce = $idInstitucion;
            $modulo->activo = true;
            if (!$modulo->fecha_vigencia_fin || $modulo->fecha_vigencia_fin->isPast()) {
                $modulo->fecha_vigencia_fin = now()->addYear()->toDateString();
            }
            $modulo->provisionado_at = now();
            $modulo->ultimo_error = null;
            $modulo->save();
        }

        return array_merge($eduexce, ['school' => $school]);
    }

    public function desactivarLicenciaEduexce(int $idInstitucion): array
    {
        $result = $this->eduexceApi->desactivarInstitucionEduexce($idInstitucion);

        $modulo = EmpresaModuloEduexce::where('id_institucion_eduexce', $idInstitucion)->first();
        if ($modulo) {
            $modulo->activo = false;
            $modulo->save();
        }

        return $result;
    }

    /** Alinea usuario.contrasena en School con el hash de la institución en EduExce. */
    private function syncAdminPasswordFromEduexce(int $idInstitucion): void
    {
        try {
            $inst = $this->eduexceApi->obtenerInstitucionEduexce($idInstitucion);
            $correo = strtolower(trim((string) ($inst['correo'] ?? '')));
            $hash = $inst['password_hash'] ?? null;

            if ($correo === '' || !$hash || !str_starts_with((string) $hash, '$2')) {
                return;
            }

            $user = User::where('email', $correo)->first();
            if ($user) {
                $user->contrasena = (string) $hash;
                $user->save();
            }
        } catch (\Throwable $e) {
            Log::debug('No se pudo sincronizar contraseña School desde EduExce', [
                'id_institucion_eduexce' => $idInstitucion,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Solo instituciones rechazadas desde ERP (módulo provisionado con activo=false).
     * Pendientes y aprobadas pueden iniciar sesión con normalidad.
     *
     * @return array{error: string, code: string}|null
     */
    public function loginBlockedByLicenciaInactiva(int $companyId): ?array
    {
        $modulo = EmpresaModuloEduexce::where('id_empresa', $companyId)->first();

        if ($modulo === null
            || $modulo->id_institucion_eduexce === null
            || $modulo->activo) {
            return null;
        }

        return [
            'error' => 'La licencia de EduExce se ha inactivado. Contacte a Virtual Technology.',
            'code' => 'LICENCIA_ICFES_INACTIVA',
        ];
    }
}
