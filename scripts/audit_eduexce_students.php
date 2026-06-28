<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\Company;
use App\Models\EmpresaModuloEduexce;
use App\Models\PersonaEduexce;
use Illuminate\Support\Facades\DB;

echo "=== Instituciones EduExce en School ===\n";
foreach (EmpresaModuloEduexce::all() as $m) {
    $c = Company::find($m->id_empresa);
    $aprendices = ActivationCompanyUser::query()
        ->where('company_id', $m->id_empresa)
        ->whereHas('roles', fn ($q) => $q->where('name', 'APRENDIZ'))
        ->count();
    $pe = PersonaEduexce::where('id_empresa', $m->id_empresa)->count();
    echo sprintf(
        "empresa=%d %s | edx=%s activo=%d | aprendices_school=%d persona_eduexce=%d\n",
        $m->id_empresa,
        $c->razonSocial ?? '?',
        $m->id_institucion_eduexce ?? 'null',
        (int) $m->activo,
        $aprendices,
        $pe
    );
}

echo "\n=== Total aprendices por rol en todo School ===\n";
$total = DB::table('activation_company_users as a')
    ->join('model_has_roles as m', function ($j) {
        $j->on('m.model_id', '=', 'a.id')->where('m.model_type', '=', 'App\\Models\\ActivationCompanyUser');
    })
    ->join('roles as r', 'r.id', '=', 'm.role_id')
    ->where('r.name', 'APRENDIZ')
    ->count();
echo "aprendices_total={$total}\n";
