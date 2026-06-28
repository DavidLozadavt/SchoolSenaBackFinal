<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\EmpresaModuloEduexce;
use App\Services\EduExceApiService;
use App\Services\EduExceInstitucionService;
use App\Services\EduExceLicenciaService;
use App\Support\EduExceAuthorization;
use App\Support\EduExceErrorMessage;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EduExceIntegrationController extends Controller
{
    public function __construct(
        private EduExceApiService $eduexceApi,
        private EduExceInstitucionService $institucionService,
        private EduExceLicenciaService $licenciaService
    ) {
    }

    private function assertModuloPermission(): ?JsonResponse
    {
        if (!EduExceAuthorization::canModuloIcfes()) {
            return response()->json([
                'error' => 'No autorizado. Conectar ICFES está disponible solo para instituciones EduExce, no para centros de formación SENA.',
            ], 403);
        }
        return null;
    }

    private function getModulo(int $companyId): EmpresaModuloEduexce
    {
        return $this->licenciaService->getOrCreateModulo($companyId);
    }

    private function licenciaContexto(int $companyId): array
    {
        return $this->licenciaService->licenciaOperativa($companyId);
    }

    private function conteos(bool $licenciaActiva, array $ctx): array
    {
        $estudiantesEduexce = 0;

        if (!empty($ctx['external_school_id'])) {
            try {
                $resumen = $this->eduexceApi->resumenInstitucion((int) $ctx['external_school_id']);
                $estudiantesEduexce = (int) ($resumen['total_estudiantes'] ?? 0);
            } catch (\Throwable $e) {
                Log::debug('No se pudo obtener total estudiantes EduExce', ['message' => $e->getMessage()]);
            }
        } elseif (!empty($ctx['id_institucion_eduexce'])) {
            try {
                $inst = $this->eduexceApi->obtenerInstitucionEduexce((int) $ctx['id_institucion_eduexce']);
                $estudiantesEduexce = (int) ($inst['total_estudiantes'] ?? 0);
            } catch (\Throwable $e) {
                Log::debug('No se pudo obtener total estudiantes EduExce por id', ['message' => $e->getMessage()]);
            }
        }

        return [
            'estudiantes_eduexce' => $estudiantesEduexce,
            'total_estudiantes' => $estudiantesEduexce,
        ];
    }

    public function configuracion(): JsonResponse
    {
        if ($deny = $this->assertModuloPermission()) {
            return $deny;
        }

        $companyId = KeyUtil::idCompany();
        $company = Company::find($companyId);
        $ctx = $this->licenciaContexto($companyId);
        $licenciaActiva = $ctx['licencia_activa'];

        $conteos = $this->conteos($licenciaActiva, $ctx);
        $estadisticas = null;

        if ($licenciaActiva && !empty($ctx['external_school_id'])) {
            try {
                $resumen = $this->eduexceApi->resumenInstitucion((int) $ctx['external_school_id']);
                $estadisticas = $resumen['estadisticas'] ?? null;
                $totalEstudiantes = (int) ($resumen['total_estudiantes'] ?? 0);
                $conteos = [
                    'estudiantes_eduexce' => $totalEstudiantes,
                    'total_estudiantes' => $totalEstudiantes,
                ];
            } catch (\Throwable $e) {
                Log::debug('No se pudo obtener resumen ICFES en configuracion', ['message' => $e->getMessage()]);
            }
        }

        return response()->json([
            'institucion' => [
                'id_empresa' => $companyId,
                'nombre' => $company?->razonSocial,
                'nit' => $company?->nit,
                'email' => $company?->email,
            ],
            'servicio_eduexce_activo' => $licenciaActiva,
            'licencia_activa' => $licenciaActiva,
            'licencia_rechazada' => (bool) ($ctx['licencia_rechazada'] ?? false),
            'licencia_gestionada_por_vt' => true,
            'fecha_vigencia_fin' => $ctx['fecha_vigencia_fin'],
            'id_institucion_eduexce' => $ctx['id_institucion_eduexce'],
            'provisionado_at' => $ctx['provisionado_at'],
            'ultimo_error' => $ctx['ultimo_error']
                ? EduExceErrorMessage::forUser($ctx['ultimo_error'])
                : null,
            'eduexce_web_url' => env('EDUEXCE_WEB_URL', 'http://localhost:5174'),
            'conteos' => $conteos,
            'estadisticas' => $estadisticas,
        ]);
    }

    public function estadoConexion(): JsonResponse
    {
        return $this->configuracion();
    }

    public function panelSso(): JsonResponse
    {
        if ($deny = $this->assertModuloPermission()) {
            return $deny;
        }

        $companyId = KeyUtil::idCompany();
        $ctx = $this->licenciaContexto($companyId);

        if (!$ctx['licencia_activa']) {
            return response()->json([
                'error' => 'La licencia de EduExce se ha inactivado. Contacte a Virtual Technology.',
                'code' => 'LICENCIA_ICFES_INACTIVA',
            ], 403);
        }

        try {
            $sso = $this->eduexceApi->generarSsoSchool($ctx['external_school_id']);
            $webBase = rtrim(env('EDUEXCE_WEB_URL', 'http://localhost:5174'), '/');
            $token = $sso['token'] ?? null;

            if (!$token) {
                return response()->json(['error' => 'EduExce no devolvió token de acceso'], 502);
            }

            $redirectPath = $sso['redirect_path'] ?? '/dashboard';
            $query = http_build_query(array_filter([
                'token' => $token,
                'next' => $redirectPath,
                'inst' => $sso['nombre_institucion'] ?? ($sso['admin']['nombre_institucion'] ?? null),
                'id' => isset($sso['id_institucion']) ? (string) $sso['id_institucion'] : null,
            ], fn ($v) => $v !== null && $v !== ''));

            $panelUrl = $webBase . '/auth/school-sso?' . $query;

            return response()->json([
                'url' => $panelUrl,
                'redirect_path' => $redirectPath,
                'nombre_institucion' => $sso['nombre_institucion'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error SSO panel EduExce', ['company_id' => $companyId, 'message' => $e->getMessage()]);

            return response()->json(['error' => EduExceErrorMessage::forUser($e->getMessage())], 502);
        }
    }

    public function estadisticas(): JsonResponse
    {
        if ($deny = $this->assertModuloPermission()) {
            return $deny;
        }

        $companyId = KeyUtil::idCompany();
        $ctx = $this->licenciaContexto($companyId);

        if (!$ctx['licencia_activa']) {
            return response()->json([
                'error' => 'La licencia de EduExce se ha inactivado. Contacte a Virtual Technology.',
                'code' => 'LICENCIA_ICFES_INACTIVA',
            ], 403);
        }

        try {
            $company = Company::findOrFail($companyId);
            $modulo = $this->getModulo($companyId);
            $this->institucionService->ensureInstitucionProvisionada($modulo, $company);
            $resumen = $this->eduexceApi->resumenInstitucion($ctx['external_school_id']);

            return response()->json($resumen);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo estadísticas EduExce', ['message' => $e->getMessage()]);

            return response()->json(['error' => EduExceErrorMessage::forUser($e->getMessage())], 502);
        }
    }
}
