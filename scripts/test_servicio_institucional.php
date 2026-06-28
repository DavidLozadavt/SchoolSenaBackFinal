<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;

$companyId = 1;
$admin = ActivationCompanyUser::with('company', 'roles.permissions')
    ->where('company_id', $companyId)
    ->whereHas('roles', fn ($q) => $q->where('name', 'like', '%Admin%'))
    ->first();

if (!$admin) {
    echo json_encode(['error' => 'Admin no encontrado']);
    exit(1);
}

$user = User::find($admin->user_id);
$perms = $admin->roles->pluck('permissions')->flatten()->unique('id')->pluck('name');
$token = JWTAuth::claims([
    'idCompany' => $companyId,
    'roles' => $admin->roles->pluck('name'),
    'permissions' => $perms,
    'company' => $admin->company,
])->fromUser($user);

$base = 'http://127.0.0.1:8000/api';
$h = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];

$results = [];

$cfg = Http::withHeaders($h)->get("{$base}/eduexce/configuracion");
$results['configuracion'] = ['status' => $cfg->status(), 'body' => $cfg->json()];

$off = Http::withHeaders($h)->post("{$base}/eduexce/servicio/desactivar");
$results['desactivar'] = ['status' => $off->status(), 'body' => $off->json()];

$cfgOff = Http::withHeaders($h)->get("{$base}/eduexce/configuracion");
$results['config_despues_off'] = $cfgOff->json('servicio_eduexce_activo');

$on = Http::timeout(120)->withHeaders($h)->post("{$base}/eduexce/servicio/activar");
$results['activar'] = ['status' => $on->status(), 'body' => $on->json()];

$ap = Http::withHeaders($h)->get("{$base}/eduexce/aprendices");
$conectados = collect($ap->json())->where('estado_icfes', 'conectado')->count();
$results['aprendices_conectados'] = $conectados;

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
