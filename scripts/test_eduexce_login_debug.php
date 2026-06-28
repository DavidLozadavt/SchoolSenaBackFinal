<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;

$doc = '1058547557';
$user = User::whereHas('persona', fn ($q) => $q->where('identificacion', $doc))->first();
$testPassword = 'TestIcfes2026!';

echo "Full hash: " . $user->contrasena . PHP_EOL;
echo "Hash check: " . (password_verify($testPassword, $user->contrasena) ? 'OK' : 'FAIL') . PHP_EOL;

$eduexceUrl = rtrim(env('EDUEXCE_API_URL', 'http://localhost:3333'), '/');
$apiKey = env('EDUEXCE_INTEGRATION_API_KEY', 'School_EduExce_Integration_2026');

$prov = Http::withHeaders(['X-Integration-Token' => $apiKey])
    ->post("{$eduexceUrl}/integracion/estudiante/provisionar", [
        'external_school_id' => 1,
        'external_persona_id' => 72,
        'tipo_documento' => 'CC',
        'numero_documento' => $doc,
        'nombre' => 'ALEJANDRO',
        'apellido' => 'SUAREZ',
        'correo' => 'test@test.local',
        'password_hash' => $user->contrasena,
        'password_tipo' => 'school_sync',
    ]);
echo "Provision: " . $prov->status() . ' ' . $prov->body() . PHP_EOL;

$login = Http::post("{$eduexceUrl}/estudiante/login", [
    'numero_documento' => $doc,
    'password' => $testPassword,
]);
echo "Login: " . $login->status() . ' ' . $login->body() . PHP_EOL;
