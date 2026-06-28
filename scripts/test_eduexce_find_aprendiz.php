<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ActivationCompanyUser;
use App\Models\Person;
use App\Models\User;
use Spatie\Permission\Models\Role;

$activation = ActivationCompanyUser::query()
    ->whereHas('roles', fn ($q) => $q->where('name', 'APRENDIZ'))
    ->with(['user.persona', 'company'])
    ->first();

if (!$activation || !$activation->user?->persona) {
    echo json_encode(['error' => 'NO_APRENDIZ'], JSON_PRETTY_PRINT);
    exit(1);
}

$persona = $activation->user->persona;
$user = $activation->user;

echo json_encode([
    'id_persona' => $persona->id,
    'identificacion' => $persona->identificacion,
    'nombre1' => $persona->nombre1,
    'apellido1' => $persona->apellido1,
    'email' => $persona->email ?? $user->email,
    'user_id' => $user->id,
    'company_id' => $activation->company_id,
    'company_name' => $activation->company?->razonSocial,
    'has_password' => !empty($user->contrasena),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
