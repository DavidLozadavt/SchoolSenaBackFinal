<?php

namespace App\Http\Controllers;

use App\Models\EmpresaModuloEduexce;
use App\Services\EduExceApiService;
use App\Services\EduExceLicenciaService;
use App\Support\EduExceAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EduExceLicenciaController extends Controller
{
    public function __construct(
        private EduExceLicenciaService $licenciaService,
        private EduExceApiService $eduexceApi
    ) {
    }

    private function assertLicenciaPermission(): ?JsonResponse
    {
        if (!EduExceAuthorization::canLicenciaIcfes()) {
            return response()->json([
                'error' => 'No autorizado. Se requiere permiso LICENCIA_ICFES (Administrador VT).',
            ], 403);
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->assertLicenciaPermission()) {
            return $deny;
        }

        $busqueda = trim((string) $request->input('q', ''));
        $institucionesEduexce = [];

        try {
            $respuesta = $this->eduexceApi->listarInstituciones('nativas');
            $lista = $respuesta['instituciones'] ?? [];
            $modulosEduexce = EmpresaModuloEduexce::whereNotNull('id_institucion_eduexce')
                ->get()
                ->keyBy('id_institucion_eduexce');

            $institucionesEduexce = collect($lista)
                ->filter(function (array $inst) use ($busqueda) {
                    if ($busqueda === '') {
                        return true;
                    }
                    $haystack = strtolower(implode(' ', [
                        $inst['nombre_institucion'] ?? '',
                        $inst['codigo_dane'] ?? '',
                        $inst['correo'] ?? '',
                        $inst['ciudad'] ?? '',
                    ]));

                    return str_contains($haystack, strtolower($busqueda));
                })
                ->map(function (array $inst) use ($modulosEduexce) {
                    $modulo = $modulosEduexce->get($inst['id_institucion']);

                    return [
                        'id_institucion_eduexce' => $inst['id_institucion'],
                        'nombre' => $inst['nombre_institucion'],
                        'codigo_dane' => $inst['codigo_dane'] ?? null,
                        'email' => $inst['correo'] ?? null,
                        'ciudad' => $inst['ciudad'] ?? null,
                        'departamento' => $inst['departamento'] ?? null,
                        'licencia_activa' => $modulo?->licenciaVigente() ?? false,
                        'fecha_vigencia_fin' => $modulo?->fecha_vigencia_fin,
                        'acceso_panel' => $modulo !== null,
                        'total_aprendices' => (int) ($inst['total_estudiantes'] ?? 0),
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('No se pudieron cargar instituciones EduExce', ['message' => $e->getMessage()]);

            return response()->json([
                'error' => 'No se pudo conectar con EduExce. Verifique que el backend esté activo en '
                    . rtrim(env('EDUEXCE_API_URL', 'http://127.0.0.1:3333'), '/')
                    . ' (use 127.0.0.1, no localhost).',
                'detalle' => $e->getMessage(),
                'instituciones_eduexce' => [],
                'total' => 0,
            ], 502);
        }

        return response()->json([
            'instituciones_eduexce' => $institucionesEduexce,
            'total' => count($institucionesEduexce),
        ]);
    }

    public function activarEduexce(int $idInstitucion): JsonResponse
    {
        if ($deny = $this->assertLicenciaPermission()) {
            return $deny;
        }

        try {
            $result = $this->licenciaService->activarLicenciaEduexce($idInstitucion);
            $school = $result['school'] ?? [];
            $email = $school['email_admin'] ?? null;
            $mensaje = 'Licencia ICFES activada para la institución.';
            if ($email) {
                $mensaje .= " Acceso al panel creado para {$email}.";
            }

            return response()->json([
                'message' => $mensaje,
                'licencia_activa' => true,
                'acceso_panel' => $school,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function desactivarEduexce(int $idInstitucion): JsonResponse
    {
        if ($deny = $this->assertLicenciaPermission()) {
            return $deny;
        }

        try {
            $this->licenciaService->desactivarLicenciaEduexce($idInstitucion);

            return response()->json([
                'message' => 'Licencia ICFES desactivada para la institución.',
                'licencia_activa' => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
