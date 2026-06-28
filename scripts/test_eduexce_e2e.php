<?php

/**
 * Prueba E2E integración School ↔ EduExce
 * Uso: php scripts/test_eduexce_e2e.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\Company;
use App\Models\EmpresaModuloEduexce;
use App\Models\Person;
use App\Models\PersonaEduexce;
use App\Models\User;
use App\Permission\PermissionConst;
use App\Services\EduExceApiService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;

$results = [];
$ok = function (string $step, $detail = null) use (&$results) {
    $results[] = ['step' => $step, 'status' => 'OK', 'detail' => $detail];
};
$fail = function (string $step, $detail) use (&$results) {
    $results[] = ['step' => $step, 'status' => 'FAIL', 'detail' => $detail];
};

// --- 1. Datos aprendiz ---
$activation = ActivationCompanyUser::query()
    ->whereHas('roles', fn ($q) => $q->where('name', 'APRENDIZ'))
    ->where('company_id', 1)
    ->with(['user.persona', 'company'])
    ->first();

if (!$activation?->user?->persona) {
    $fail('1_buscar_aprendiz', 'No hay aprendiz en company_id=1');
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

$persona = $activation->user->persona;
$user = $activation->user;
$companyId = (int) $activation->company_id;
$idPersona = (int) $persona->id;

$ok('1_buscar_aprendiz', [
    'id_persona' => $idPersona,
    'documento' => $persona->identificacion,
    'nombre' => trim("{$persona->nombre1} {$persona->apellido1}"),
    'company_id' => $companyId,
]);

// --- 2. Permiso GESTION_ICFES en rol Admin ---
$perm = Permission::firstOrCreate(
    ['name' => PermissionConst::GESTION_ICFES, 'guard_name' => 'web'],
    ['description' => 'Módulo ICFES / EduExce']
);

$adminActivation = ActivationCompanyUser::query()
    ->where('company_id', $companyId)
    ->where('user_id', 1)
    ->first();

if (!$adminActivation) {
    $adminActivation = ActivationCompanyUser::query()
        ->where('company_id', $companyId)
        ->whereHas('roles', fn ($q) => $q->where('name', 'like', '%Admin%'))
        ->first();
}

if ($adminActivation) {
    $role = $adminActivation->roles->first();
    if ($role && !$role->hasPermissionTo(PermissionConst::GESTION_ICFES)) {
        $role->givePermissionTo(PermissionConst::GESTION_ICFES);
    }
    $ok('2_permiso_icfes', 'Asignado a rol ' . ($role?->name ?? 'N/A'));
} else {
    $fail('2_permiso_icfes', 'No se encontró activación admin');
}

// --- 3. EduExce health ---
$eduexceUrl = rtrim(env('EDUEXCE_API_URL', 'http://localhost:3333'), '/');
try {
    $health = Http::timeout(10)->get("{$eduexceUrl}/health");
    if ($health->successful()) {
        $ok('3_eduexce_health', $health->json('status'));
    } else {
        $fail('3_eduexce_health', 'HTTP ' . $health->status());
    }
} catch (Throwable $e) {
    $fail('3_eduexce_health', $e->getMessage());
}

// --- 4. Provisionar institución vía API integración ---
$apiKey = env('EDUEXCE_INTEGRATION_API_KEY', 'School_EduExce_Integration_2026');
$company = Company::find($companyId);
try {
    $provInst = Http::timeout(30)
        ->withHeaders(['X-Integration-Token' => $apiKey, 'Accept' => 'application/json'])
        ->post("{$eduexceUrl}/integracion/institucion/provisionar", [
            'external_school_id' => $companyId,
            'nombre_institucion' => $company->razonSocial,
            'codigo_dane' => 'NIT-' . ($company->nit ?? $companyId),
            'correo' => $company->email,
            'ciudad' => 'Popayan',
            'departamento' => 'Cauca',
            'jornada' => 'completa',
        ]);

    if ($provInst->successful()) {
        $ok('4_provisionar_institucion', $provInst->json());
        EmpresaModuloEduexce::updateOrCreate(
            ['id_empresa' => $companyId],
            [
                'id_institucion_eduexce' => $provInst->json('id_institucion'),
                'activo' => true,
                'fecha_vigencia_fin' => now()->addYear(),
                'provisionado_at' => now(),
            ]
        );
    } else {
        $fail('4_provisionar_institucion', $provInst->json('error') ?? $provInst->body());
    }
} catch (Throwable $e) {
    $fail('4_provisionar_institucion', $e->getMessage());
}

// --- 5. Provisionar estudiante (school_sync password) ---
try {
    $provEst = Http::timeout(30)
        ->withHeaders(['X-Integration-Token' => $apiKey, 'Accept' => 'application/json'])
        ->post("{$eduexceUrl}/integracion/estudiante/provisionar", [
            'external_school_id' => $companyId,
            'external_persona_id' => $idPersona,
            'tipo_documento' => 'CC',
            'numero_documento' => $persona->identificacion,
            'nombre' => trim($persona->nombre1 . ' ' . ($persona->nombre2 ?? '')),
            'apellido' => trim($persona->apellido1 . ' ' . ($persona->apellido2 ?? '')),
            'correo' => $persona->email ?? $user->email,
            'password_hash' => $user->contrasena,
            'password_tipo' => 'school_sync',
        ]);

    if ($provEst->successful()) {
        $ok('5_provisionar_estudiante', $provEst->json());
        PersonaEduexce::updateOrCreate(
            ['id_persona' => $idPersona, 'id_empresa' => $companyId],
            [
                'id_usuario_eduexce' => $provEst->json('id_usuario'),
                'estado' => 'conectado',
                'habilitado_at' => now(),
                'last_sync_at' => now(),
            ]
        );
    } else {
        $fail('5_provisionar_estudiante', $provEst->json('error') ?? $provEst->body());
    }
} catch (Throwable $e) {
    $fail('5_provisionar_estudiante', $e->getMessage());
}

// --- 6. Resumen estadísticas ---
try {
    $resumen = Http::timeout(15)
        ->withHeaders(['X-Integration-Token' => $apiKey])
        ->get("{$eduexceUrl}/integracion/institucion/{$companyId}/resumen");
    $resumen->successful()
        ? $ok('6_resumen_estadisticas', $resumen->json())
        : $fail('6_resumen_estadisticas', $resumen->body());
} catch (Throwable $e) {
    $fail('6_resumen_estadisticas', $e->getMessage());
}

// --- 7. School BFF con JWT (habilitar-aprendiz vía HTTP) ---
$adminUser = User::find($adminActivation?->user_id ?? 1);
if ($adminUser) {
    $adminAct = ActivationCompanyUser::with('company', 'roles.permissions')
        ->where('user_id', $adminUser->id)
        ->where('company_id', $companyId)
        ->first();

    if ($adminAct) {
        $roles = $adminAct->roles;
        $permissions = $roles->pluck('permissions')->flatten()->unique('id')->pluck('name');
        $token = JWTAuth::claims([
            'idCompany' => $companyId,
            'roles' => $roles->pluck('name'),
            'permissions' => $permissions,
            'company' => $adminAct->company,
        ])->fromUser($adminUser);

        $schoolBase = 'http://127.0.0.1:8000/api';

        try {
            $estadoConn = Http::timeout(15)
                ->withToken($token)
                ->get("{$schoolBase}/eduexce/estado-conexion");
            $estadoConn->successful()
                ? $ok('7_school_estado_conexion', $estadoConn->json())
                : $fail('7_school_estado_conexion', $estadoConn->status() . ' ' . $estadoConn->body());
        } catch (Throwable $e) {
            $fail('7_school_estado_conexion', $e->getMessage());
        }

        try {
            $stats = Http::timeout(15)
                ->withToken($token)
                ->get("{$schoolBase}/eduexce/estadisticas");
            $stats->successful()
                ? $ok('8_school_estadisticas', $stats->json())
                : $fail('8_school_estadisticas', $stats->status() . ' ' . $stats->body());
        } catch (Throwable $e) {
            $fail('8_school_estadisticas', $e->getMessage());
        }

        try {
            $estAprendiz = Http::timeout(15)
                ->withToken($token)
                ->get("{$schoolBase}/eduexce/aprendiz/{$idPersona}/estado");
            $estAprendiz->successful()
                ? $ok('9_school_estado_aprendiz', $estAprendiz->json())
                : $fail('9_school_estado_aprendiz', $estAprendiz->body());
        } catch (Throwable $e) {
            $fail('9_school_estado_aprendiz', $e->getMessage());
        }
    } else {
        $fail('7_school_bff', 'Sin activación admin para company');
    }
} else {
    $fail('7_school_bff', 'Admin user no encontrado');
}

// --- 10. Bloqueo licencia: desactivar → login 403 → reactivar ---
$testPassword = 'TestIcfes2026!';

try {
    Http::withHeaders(['X-Integration-Token' => $apiKey])
        ->post("{$eduexceUrl}/integracion/institucion/desactivar", ['external_school_id' => $companyId]);

    $loginBlocked = Http::timeout(10)->post("{$eduexceUrl}/estudiante/login", [
        'numero_documento' => $persona->identificacion,
        'password' => $testPassword ?? 'cualquier-cosa',
    ]);

    if ($loginBlocked->status() === 403 && str_contains($loginBlocked->body(), 'INSTITUCION_INACTIVA')) {
        $ok('10_bloqueo_licencia_403', $loginBlocked->json());
    } else {
        $fail('10_bloqueo_licencia_403', 'Esperaba 403 INSTITUCION_INACTIVA, got ' . $loginBlocked->status() . ' ' . substr($loginBlocked->body(), 0, 200));
    }

    Http::withHeaders(['X-Integration-Token' => $apiKey])
        ->post("{$eduexceUrl}/integracion/institucion/reactivar", ['external_school_id' => $companyId]);
    $ok('10_reactivar_licencia', 'Institución reactivada');
} catch (Throwable $e) {
    $fail('10_bloqueo_licencia', $e->getMessage());
}

// --- 11. Login estudiante EduExce (contraseña temporal de prueba) ---
$user->contrasena = bcrypt($testPassword);
$user->save();

try {
    $reprov = Http::timeout(15)
        ->withHeaders(['X-Integration-Token' => $apiKey])
        ->post("{$eduexceUrl}/integracion/estudiante/provisionar", [
            'external_school_id' => $companyId,
            'external_persona_id' => $idPersona,
            'tipo_documento' => 'CC',
            'numero_documento' => $persona->identificacion,
            'nombre' => trim($persona->nombre1),
            'apellido' => trim($persona->apellido1),
            'correo' => $persona->email ?? $user->email,
            'password_hash' => $user->contrasena,
            'password_tipo' => 'school_sync',
        ]);

    if (!$reprov->successful()) {
        $fail('11_reprovision_estudiante', $reprov->body());
    }

    $loginOk = Http::timeout(10)->post("{$eduexceUrl}/estudiante/login", [
        'numero_documento' => $persona->identificacion,
        'password' => $testPassword,
    ]);

    if ($loginOk->successful() && $loginOk->json('token')) {
        $ok('11_login_estudiante_movil', [
            'token_recibido' => true,
            'id_usuario' => $loginOk->json('usuario.id_usuario'),
        ]);
    } else {
        $fail('11_login_estudiante_movil', $loginOk->status() . ' ' . substr($loginOk->body(), 0, 200));
    }
} catch (Throwable $e) {
    $fail('11_login_estudiante_movil', $e->getMessage());
}

// --- Resumen ---
$passed = count(array_filter($results, fn ($r) => $r['status'] === 'OK'));
$failed = count(array_filter($results, fn ($r) => $r['status'] === 'FAIL'));

echo json_encode([
    'summary' => ['passed' => $passed, 'failed' => $failed, 'total' => count($results)],
    'aprendiz' => ['documento' => $persona->identificacion, 'id_persona' => $idPersona],
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

exit($failed > 0 ? 1 : 0);
