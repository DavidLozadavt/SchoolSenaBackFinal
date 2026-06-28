<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

foreach (['ficha', 'aperturarprograma'] as $table) {
    echo "=== {$table} ===\n";
    $cols = DB::select("SHOW COLUMNS FROM {$table}");
    foreach ($cols as $c) {
        if (stripos($c->Field, 'jorn') !== false || stripos($c->Field, 'asign') !== false) {
            echo "  {$c->Field}\n";
        }
    }
}
