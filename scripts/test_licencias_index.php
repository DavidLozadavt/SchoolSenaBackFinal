<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\User;
use App\Services\EduExceApiService;
use App\Support\EduExceAuthorization;
use App\Util\KeyUtil;
use Illuminate\Support\Facades\Auth;

echo "=== Licencias index debug ===\n";
echo 'Companies: ' . Company::count() . "\n";

$user = User::where('email', 'adminvt@virtualt.org')->first();
if (!$user) {
    echo "adminvt not found\n";
    exit(1);
}

Auth::login($user);
KeyUtil::setUser($user);
KeyUtil::setCompanyId(1);

$perms = collect(KeyUtil::permissions())->map(fn ($p) => strtoupper((string) $p));
echo 'LICENCIA_ICFES: ' . ($perms->contains('LICENCIA_ICFES') ? 'YES' : 'NO') . "\n";
echo 'canLicenciaIcfes: ' . (EduExceAuthorization::canLicenciaIcfes() ? 'YES' : 'NO') . "\n";

$ctrl = app(\App\Http\Controllers\EduExceLicenciaController::class);
$req = \Illuminate\Http\Request::create('/eduexce/licencias', 'GET');
$resp = $ctrl->index($req);
$data = json_decode($resp->getContent(), true);
echo 'HTTP would be 200, regionales=' . count($data['regionales'] ?? []) . ', eduexce=' . count($data['instituciones_eduexce'] ?? []) . "\n";
if (!empty($data['regionales'][0])) {
    echo 'First regional: ' . ($data['regionales'][0]['nombre'] ?? '?') . "\n";
}
