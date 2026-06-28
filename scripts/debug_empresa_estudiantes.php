<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\PersonaEduexce;
use Illuminate\Support\Facades\DB;

$companyId = (int) ($argv[1] ?? 3);

echo "empresa={$companyId}\n";
echo 'activations_total=' . ActivationCompanyUser::where('company_id', $companyId)->count() . "\n";

$roles = DB::table('activation_company_users as a')
    ->join('model_has_roles as m', function ($j) {
        $j->on('m.model_id', '=', 'a.id')->where('m.model_type', '=', 'App\\Models\\ActivationCompanyUser');
    })
    ->join('roles as r', 'r.id', '=', 'm.role_id')
    ->where('a.company_id', $companyId)
    ->select('r.name', DB::raw('count(*) as c'))
    ->groupBy('r.name')
    ->get();
echo "roles:\n";
foreach ($roles as $r) {
    echo "  {$r->name}={$r->c}\n";
}

echo 'persona_eduexce=' . PersonaEduexce::where('id_empresa', $companyId)->count() . "\n";
echo 'persona_eduexce_conectados=' . PersonaEduexce::where('id_empresa', $companyId)->where('estado', 'conectado')->count() . "\n";
