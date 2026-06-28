<?php

/**
 * Provisiona institución y aprendices de una empresa School en EduExce (BD local).
 * Uso: php scripts/bootstrap_eduexce_empresa.php 3
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\Company;
use App\Models\EmpresaModuloEduexce;
use App\Models\PersonaEduexce;
use Illuminate\Support\Facades\Http;

$companyId = (int) ($argv[1] ?? 0);
if ($companyId <= 0) {
    fwrite(STDERR, "Uso: php scripts/bootstrap_eduexce_empresa.php <id_empresa>\n");
    exit(1);
}

$company = Company::find($companyId);
if (!$company) {
    fwrite(STDERR, "Empresa {$companyId} no encontrada\n");
    exit(1);
}

$eduexceUrl = rtrim(env('EDUEXCE_API_URL', 'http://127.0.0.1:3333'), '/');
$apiKey = env('EDUEXCE_INTEGRATION_API_KEY', 'School_EduExce_Integration_2026');
$headers = ['X-Integration-Token' => $apiKey, 'Accept' => 'application/json'];

echo "=== Bootstrap EduExce empresa {$companyId} ({$company->razonSocial}) ===\n";

$health = Http::timeout(10)->get("{$eduexceUrl}/health");
if (!$health->successful()) {
    fwrite(STDERR, "EduExce API no responde en {$eduexceUrl}\n");
    exit(1);
}
echo "API OK\n";

$provInst = Http::timeout(30)->withHeaders($headers)->post("{$eduexceUrl}/integracion/institucion/provisionar", [
    'external_school_id' => $companyId,
    'nombre_institucion' => $company->razonSocial,
    'codigo_dane' => $company->nit ? "NIT-{$company->nit}" : "SCH-{$companyId}",
    'correo' => $company->email,
    'ciudad' => 'Sin especificar',
    'departamento' => 'Sin especificar',
    'direccion' => $company->direccion ?? null,
    'telefono' => $company->telefono ?? null,
    'jornada' => 'completa',
]);

if (!$provInst->successful()) {
    fwrite(STDERR, 'provisionar institucion: ' . ($provInst->json('error') ?? $provInst->body()) . "\n");
    exit(1);
}

$idInst = (int) ($provInst->json('id_institucion') ?? 0);
echo "Institución provisionada id_institucion={$idInst}\n";

$modulo = EmpresaModuloEduexce::firstOrCreate(['id_empresa' => $companyId]);
$modulo->id_institucion_eduexce = $idInst;
$modulo->provisionado_at = now();
$modulo->ultimo_error = null;
$modulo->save();

$aprendices = ActivationCompanyUser::query()
    ->where('company_id', $companyId)
    ->whereHas('roles', fn ($q) => $q->where('name', 'APRENDIZ'))
    ->with(['user.persona'])
    ->get();

$ok = 0;
$fail = 0;

foreach ($aprendices as $act) {
    $persona = $act->user?->persona;
    $user = $act->user;
    if (!$persona || !$user) {
        continue;
    }

    $idPersona = (int) $persona->id;
    $provEst = Http::timeout(30)->withHeaders($headers)->post("{$eduexceUrl}/integracion/estudiante/provisionar", [
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
        PersonaEduexce::updateOrCreate(
            ['id_persona' => $idPersona, 'id_empresa' => $companyId],
            [
                'external_school_id' => $companyId,
                'id_usuario_eduexce' => $provEst->json('id_usuario'),
                'estado' => 'conectado',
                'habilitado_at' => now(),
                'last_sync_at' => now(),
            ]
        );
        $ok++;
        echo "  OK aprendiz {$persona->identificacion}\n";
    } else {
        $fail++;
        echo "  FAIL {$persona->identificacion}: " . ($provEst->json('error') ?? $provEst->body()) . "\n";
    }
}

$resumen = Http::timeout(15)->withHeaders($headers)->get("{$eduexceUrl}/integracion/institucion/{$companyId}/resumen");
$total = $resumen->json('total_estudiantes') ?? '?';

echo "\nAprendices provisionados: {$ok} OK, {$fail} FAIL\n";
echo "Total estudiantes en EduExce: {$total}\n";
echo json_encode($resumen->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
