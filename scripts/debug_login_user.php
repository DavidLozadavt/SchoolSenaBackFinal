<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\ActivationCompanyUser;
use App\Models\EmpresaModuloEduexce;
use Illuminate\Support\Facades\Hash;

$login = $argv[1] ?? 'pinzadiaz@gmail.com';
$password = $argv[2] ?? 'diazsirley';

echo "=== Diagnóstico login School ===\n";
echo "Login intentado: {$login}\n\n";

$userByEmail = User::where('email', $login)->first();
$userByDoc = User::whereHas('persona', fn ($q) => $q->where('identificacion', $login))->first();

$user = $userByEmail ?? $userByDoc;

if (!$user) {
    echo "Usuario NO encontrado por email ni documento.\n";
    $like = User::where('email', 'like', '%' . explode('@', $login)[0] . '%')->limit(5)->get(['id', 'email']);
    foreach ($like as $u) {
        echo "  similar email id={$u->id} email={$u->email}\n";
    }
    $inst = EmpresaModuloEduexce::where('id_institucion_eduexce', 45)->first();
    if ($inst) {
        echo "\nEmpresa San Francisco id_empresa={$inst->id_empresa}\n";
        $activations = ActivationCompanyUser::where('company_id', $inst->id_empresa)->with('user.persona')->get();
        foreach ($activations as $a) {
            $u = $a->user;
            echo "  user_id={$a->user_id} email={$u?->email} doc={$u?->persona?->identificacion} state={$a->state_id}\n";
        }
    }
    exit(1);
}

echo "Usuario id={$user->id} email={$user->email}\n";
echo "Documento: " . ($user->persona?->identificacion ?? 'N/A') . "\n";
echo "Nombre: " . ($user->persona?->nombre1 ?? '') . "\n";
echo "Hash contrasena presente: " . (!empty($user->contrasena) ? 'sí' : 'NO') . "\n";
echo "Hash prefix: " . substr((string) $user->contrasena, 0, 7) . " len=" . strlen((string) $user->contrasena) . "\n";
echo "password_verify('{$password}'): " . (password_verify($password, $user->contrasena) ? 'OK' : 'FAIL') . "\n";
echo "Hash::check('{$password}'): " . (Hash::check($password, $user->contrasena) ? 'OK' : 'FAIL') . "\n";

foreach (['EduExceSchool2026!', 'Diazsirley', 'DIAZSIRLEY'] as $try) {
    if ($try === $password) {
        continue;
    }
    echo "  prueba '{$try}': " . (password_verify($try, $user->contrasena) ? 'OK' : 'FAIL') . "\n";
}

$activations = ActivationCompanyUser::where('user_id', $user->id)->with('company')->get();
echo "\nActivaciones (" . $activations->count() . "):\n";
foreach ($activations as $a) {
    echo "  company_id={$a->company_id} ({$a->company?->razonSocial}) state_id={$a->state_id}\n";
}

$modulo = EmpresaModuloEduexce::where('id_empresa', $activations->first()?->company_id)->first();
if ($modulo) {
    echo "\nModulo EduExce: id_edx={$modulo->id_institucion_eduexce} activo=" . ($modulo->activo ? '1' : '0') . "\n";
}

echo "\nNOTA: School usa BD MySQL local (tabla usuario.contrasena), NO Supabase.\n";
