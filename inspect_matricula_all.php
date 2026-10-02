<?php
include __DIR__ . '/vendor/autoload.php';
$app = include __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$columns = DB::select("SHOW COLUMNS FROM matricula");
foreach ($columns as $c) {
    echo "  - " . $c->Field . " (" . $c->Type . ")\n";
}
