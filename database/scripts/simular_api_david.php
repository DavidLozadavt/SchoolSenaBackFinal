<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/fichas/instructor/11/clases-asignadas', 'GET');
$controller = $app->make(App\Http\Controllers\FichaController::class);
$response = $controller->clasesAsignadasInstructor($request, 11);
$data = json_decode($response->getContent(), true);
$clases = collect($data['data'] ?? []);

$hoy = '2026-05-27';
echo "Total API: " . $clases->count() . "\n\n";

$porDia = $clases->groupBy('dia_semana');
foreach ($porDia as $dia => $items) {
    echo "=== {$dia} ({$items->count()} filas API) ===\n";
    foreach ($items->sortByDesc('fechaFinal') as $c) {
        $vig = ($c['fechaInicial'] <= $hoy && $c['fechaFinal'] >= $hoy) ? 'VIGENTE' : '';
        echo sprintf(
            "  hm#%s %s-%s | %s → %s | est=%s rest=%s %s\n",
            $c['idHorarioMateria'],
            substr($c['horaInicial'] ?? '', 0, 5),
            substr($c['horaFinal'] ?? '', 0, 5),
            $c['fechaInicial'],
            $c['fechaFinal'],
            $c['estado'] ?? '-',
            $c['sesiones_restantes'] ?? '?',
            $vig
        );
    }
}

echo "\n=== Tras dedupe por clave lógica (como front) ===\n";
// replicate dedupe: keep lowest idHorarioMateria per key
$porClave = [];
$hoyDt = new DateTime($hoy);
foreach ($clases as $c) {
    $key = implode('|', [
        $c['ficha_id'] ?? 0,
        $c['idDia'] ?? 0,
        substr($c['horaInicial'] ?? '', 0, 5),
        substr($c['horaFinal'] ?? '', 0, 5),
    ]);
    $score = 0;
    if ($c['fechaInicial'] <= $hoy && $c['fechaFinal'] >= $hoy) $score += 1000000000;
    if (($c['sesiones_restantes'] ?? 0) > 0) $score += 100000000;
    $score += strtotime($c['fechaFinal']);
    $score += $c['idHorarioMateria'];
    if (!isset($porClave[$key]) || $score > $porClave[$key]['_score']) {
        $c['_score'] = $score;
        $porClave[$key] = $c;
    }
}
foreach ($porClave as $c) {
    echo sprintf(
        "%s hm#%s fin %s rest %s\n",
        $c['dia_semana'],
        $c['idHorarioMateria'],
        $c['fechaFinal'],
        $c['sesiones_restantes'] ?? '?'
    );
}
