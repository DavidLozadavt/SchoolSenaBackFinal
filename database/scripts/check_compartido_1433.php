<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$hm = DB::table('horarioMateria')->where('id', 428)->first();
echo "horarioMateria #428:\n";
print_r($hm);

$asig = DB::table('asignacionsesion')->where('idHorarioMateria', 428)->get();
echo "\nasignacionsesion:\n";
foreach ($asig as $a) print_r((array)$a);

$slot = DB::table('horarioMateria')
    ->where('idFicha', $hm->idFicha)
    ->where('idGradoMateria', $hm->idGradoMateria)
    ->where('idDia', $hm->idDia)
    ->where('horaInicial', $hm->horaInicial)
    ->where('horaFinal', $hm->horaFinal)
    ->get(['id','idContrato']);
echo "\nSlot mismo cupo:\n";
foreach ($slot as $s) print_r((array)$s);
