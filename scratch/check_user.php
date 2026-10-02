<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ActivationCompanyUser;

$user = User::where('email', 'delozada@sena.edu.co')->first();
if (!$user) {
    echo "User not found\n";
    exit;
}
echo "User ID: " . $user->id . "\n";
echo "User Email: " . $user->email . "\n";

$activations = ActivationCompanyUser::with('roles')->where('user_id', $user->id)->get();
echo "Activations count: " . $activations->count() . "\n";
foreach ($activations as $a) {
    echo "ID: {$a->id}, Company ID: {$a->company_id}, State ID: {$a->state_id}, Start: {$a->fechaInicio}, End: {$a->fechaFin}\n";
    echo "Roles: " . $a->roles->pluck('name')->implode(', ') . "\n";
}

if (Illuminate\Support\Facades\Hash::check('123', $user->password)) {
    echo "Password '123' is CORRECT\n";
} else {
    echo "Password '123' is INCORRECT. Resetting...\n";
    $user->contrasena = Illuminate\Support\Facades\Hash::make('123');
    $user->save();
    echo "Password reset to '123' successful.\n";
}
