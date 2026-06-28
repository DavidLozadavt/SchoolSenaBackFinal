<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\CentrosFormacion;
use App\Models\Company;
use App\Services\EduExceApiService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

$empresa = Company::find(1);
echo "Empresa id=1:\n";
print_r($empresa?->only(['id', 'razonSocial', 'email', 'nit']));

echo "\nCentros:\n";
foreach (CentrosFormacion::where('idEmpresa', 1)->get() as $c) {
    $conCentro = ActivationCompanyUser::where('company_id', 1)
        ->whereHas('roles', fn ($q) => $q->whereIn('name', ['APRENDIZ', 'ESTUDIANTEUP']))
        ->whereHas('user', fn ($q) => $q->where('idCentroFormacion', $c->id))
        ->count();
    $porMatricula = DB::table('matricula')
        ->join('ficha', 'matricula.idFicha', '=', 'ficha.id')
        ->join('sedes', 'ficha.idSede', '=', 'sedes.id')
        ->where('ficha.idRegional', 1)
        ->where('sedes.idCentroFormacion', $c->id)
        ->distinct()
        ->count('matricula.idPersona');
    echo "  {$c->id} {$c->nombre} user_centro={$conCentro} matricula={$porMatricula}\n";
}

$total = ActivationCompanyUser::where('company_id', 1)
    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['APRENDIZ', 'ESTUDIANTEUP']))
    ->count();
$conCentro = ActivationCompanyUser::where('company_id', 1)
    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['APRENDIZ', 'ESTUDIANTEUP']))
    ->whereHas('user', fn ($q) => $q->whereNotNull('idCentroFormacion'))
    ->count();
echo "\nTotal aprendices regional: {$total}, con idCentroFormacion: {$conCentro}\n";

$vt = Role::where('name', 'ADMINISTRADOR VT')->first();
echo "\nADMINISTRADOR VT permisos gestión:\n";
foreach ($vt?->permissions ?? [] as $p) {
    if (str_contains($p->name, 'GESTION_') || str_contains($p->name, 'USUARIO') || str_contains($p->name, 'ROL')) {
        echo "  {$p->name}\n";
    }
}

echo "\nEduExce instituciones (origen=eduexce):\n";
try {
    $api = app(EduExceApiService::class);
    $resp = $api->listarInstituciones('eduexce');
    echo "  total=" . ($resp['total'] ?? 0) . "\n";
    foreach (($resp['instituciones'] ?? []) as $i) {
        echo "  - {$i['id_institucion']} {$i['nombre_institucion']} origen={$i['origen_cuenta']}\n";
    }
} catch (Throwable $e) {
    echo "  ERROR: {$e->getMessage()}\n";
}

echo "\nEduExce instituciones (origen=all):\n";
try {
    $api = app(EduExceApiService::class);
    $resp = $api->listarInstituciones('all');
    echo "  total=" . ($resp['total'] ?? 0) . "\n";
} catch (Throwable $e) {
    echo "  ERROR: {$e->getMessage()}\n";
}
