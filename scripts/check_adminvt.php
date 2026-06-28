<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'adminvt@virtualt.org')->first();
echo 'user id=' . $user->id . "\n";

App\Models\ActivationCompanyUser::where('user_id', $user->id)
    ->with('roles.permissions')
    ->get()
    ->each(function ($a) {
        echo "activation company={$a->company_id} state={$a->state_id} fin={$a->fechaFin}\n";
        echo '  roles: ' . $a->roles->pluck('name')->implode(', ') . "\n";
        foreach ($a->roles as $r) {
            foreach ($r->permissions as $p) {
                if (str_contains($p->name, 'ICFES') || str_contains($p->name, 'GESTION')) {
                    echo "    perm: {$p->name}\n";
                }
            }
        }
    });
