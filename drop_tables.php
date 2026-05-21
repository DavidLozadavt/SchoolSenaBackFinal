<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

Schema::dropIfExists('formulario_respuestas');
Schema::dropIfExists('formulario_opciones');
Schema::dropIfExists('formulario_preguntas');

if (Schema::hasColumn('evento', 'idFormularioInterno')) {
    Schema::table('evento', function (Blueprint $table) {
        $table->dropForeign(['idFormularioInterno']);
        $table->dropColumn('idFormularioInterno');
    });
}

Schema::dropIfExists('formularios');

DB::statement('SET FOREIGN_KEY_CHECKS=1;');
echo "Tables dropped securely.\n";
