<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\User;
use App\Util\QueryUtil;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

$user = User::where('email', 'adminvt@virtualt.org')->first();
Auth::login($user);

$user_active = ActivationCompanyUser::with('company', 'roles.permissions')
    ->where(function ($query) {
        QueryUtil::whereUser($query);
        QueryUtil::whereActive($query);
    })->first();

if (!$user_active) {
    echo "No active activation\n";
    exit(1);
}

$permissions = $user_active->roles->pluck('permissions')->flatten()->unique('id')->pluck('name');
echo 'LICENCIA_ICFES in token: ' . ($permissions->contains('LICENCIA_ICFES') ? 'YES' : 'NO') . "\n";

$token = JWTAuth::claims([
    'idCompany' => $user_active->company_id,
    'roles' => $user_active->roles->pluck('name'),
    'permissions' => $permissions,
    'company' => $user_active->company,
])->fromUser($user);

$client = \Illuminate\Support\Facades\Http::withToken($token)->get('http://127.0.0.1:8000/api/eduexce/licencias');
echo 'API HTTP ' . $client->status() . "\n";
if ($client->successful()) {
    $d = $client->json();
    echo 'regionales=' . count($d['regionales'] ?? []) . "\n";
    foreach ($d['regionales'] ?? [] as $r) {
        echo '  - ' . ($r['nombre'] ?? '?') . "\n";
    }
    echo 'instituciones_eduexce=' . count($d['instituciones_eduexce'] ?? []) . "\n";
} else {
    echo $client->body() . "\n";
}
