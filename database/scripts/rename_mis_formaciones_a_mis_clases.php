<?php

/**
 * Renombra en BD la descripción del permiso del menú:
 * "Mis formaciones" → "Mis clases" (path /ambiente-virtual/historial-raps).
 *
 * Uso:
 *   php database/scripts/rename_mis_formaciones_a_mis_clases.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$path = '/ambiente-virtual/historial-raps';
$updated = DB::table('permissions')
    ->where('path', $path)
    ->orWhere('description', 'like', '%Mis formaciones%')
    ->orWhere('description', 'like', '%Mis Formaciones%')
    ->update([
        'description' => 'Mis clases',
        'updated_at' => now(),
    ]);

// También por path exacto si description era otra cosa
$byPath = DB::table('permissions')->where('path', $path)->update([
    'description' => 'Mis clases',
    'updated_at' => now(),
]);

echo "Actualizados (like formaciones): {$updated}\n";
echo "Actualizados (path historial-raps): {$byPath}\n";
echo "Listo. Recarga el menú en el frontend.\n";
