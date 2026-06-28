<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

$email = $argv[1] ?? 'pinzadiaz@gmail.com';
$password = $argv[2] ?? 'diazsirley';
$idEdx = (int) ($argv[3] ?? 45);

$eduexceUrl = rtrim(env('EDUEXCE_API_URL', 'http://127.0.0.1:3333'), '/');

echo "=== EduExce admin/login ===\n";
$login = Http::timeout(10)->post("{$eduexceUrl}/admin/login", [
    'correo' => $email,
    'password' => $password,
]);
echo "HTTP {$login->status()}\n";
echo substr($login->body(), 0, 500) . "\n\n";

$apiKey = env('EDUEXCE_INTEGRATION_API_KEY', 'School_EduExce_Integration_2026');
$inst = Http::withHeaders(['X-Integration-Token' => $apiKey])
    ->get("{$eduexceUrl}/integracion/institucion/eduexce/{$idEdx}");
echo "=== Institución {$idEdx} ===\n";
echo "HTTP {$inst->status()}\n";
$data = $inst->json();
$hash = $data['password'] ?? $data['password_hash'] ?? null;
echo "correo=" . ($data['correo'] ?? '?') . " is_active=" . json_encode($data['is_active'] ?? null) . "\n";
if ($hash) {
    echo "hash prefix: " . substr((string) $hash, 0, 7) . "\n";
    echo "EduExce hash vs '{$password}': " . (password_verify($password, (string) $hash) ? 'OK' : 'FAIL') . "\n";
}

$user = User::where('email', $email)->first();
if ($user && $hash && password_verify($password, (string) $hash)) {
    echo "\nSincronizando contrasena School desde EduExce...\n";
    $user->contrasena = (string) $hash;
    $user->save();
    echo "School user {$user->id} actualizado.\n";
    echo "School verify: " . (password_verify($password, $user->contrasena) ? 'OK' : 'FAIL') . "\n";
} elseif ($user && $password) {
    echo "\nForzando bcrypt en School con contraseña indicada...\n";
    $user->contrasena = Hash::make($password);
    $user->save();
    echo "School verify: " . (password_verify($password, $user->contrasena) ? 'OK' : 'FAIL') . "\n";
}
