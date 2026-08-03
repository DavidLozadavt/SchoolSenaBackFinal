<?php

namespace App\Console\Commands;

use App\Models\Materia;
use App\Support\DiagnosticoActividadesRapHistoricas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostica RAPs con listado vacío cuando la competencia sí tiene actividades
 * asociadas a otros RAP hermanos (datos históricos / creación en RAP incorrecto).
 */
class DiagnosticarActividadesRap extends Command
{
    protected $signature = 'actividades:diagnosticar-rap
                            {--competencia= : Limitar a id de competencia (materia padre)}
                            {--rap= : Simular listado vacío para este RAP (diagnóstico de hermanos)}
                            {--detalle : Listar cada actividad en RAP hermano}
                            {--json : Salida JSON}';

    protected $description = 'Lista RAPs vacíos con hermanos que sí tienen actividades (compatibilidad histórica)';

    public function handle(): int
    {
        if (! Schema::hasTable('actividades') || ! Schema::hasTable('materia')) {
            $this->error('Tablas actividades/materia no disponibles.');

            return self::FAILURE;
        }

        $rapSimulado = (int) $this->option('rap');
        if ($rapSimulado > 0) {
            $diag = DiagnosticoActividadesRapHistoricas::diagnosticarListadoVacio(
                $rapSimulado,
                null,
                null,
                'artisan actividades:diagnosticar-rap --rap'
            );
            if ($this->option('json')) {
                $this->line(json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }
            if ($diag === []) {
                $this->info("RAP {$rapSimulado}: listado vacío y sin actividades en RAP hermanos.");

                return self::SUCCESS;
            }
            $this->warn("RAP {$rapSimulado}: ".count($diag).' actividad(es) en hermanos (ocultas por filtro estricto):');
            $this->table(
                ['idActividad', 'RAP_almacenado', 'RAP_solicitado', 'Competencia', 'fichas', 'Resultado'],
                collect($diag)->map(fn ($d) => [
                    $d['idActividad'],
                    $d['RAP_almacenado'],
                    $d['RAP_solicitado'],
                    $d['Competencia'],
                    implode(',', $d['fichas'] ?? []) ?: '—',
                    $d['Resultado'],
                ])->all()
            );

            return self::SUCCESS;
        }

        $compFilter = (int) $this->option('competencia');
        $detalle = (bool) $this->option('detalle');

        $rows = DB::table('actividades as a')
            ->join('materia as m', 'm.id', '=', 'a.idMateria')
            ->whereNotNull('m.idMateriaPadre')
            ->where('m.idMateriaPadre', '>', 0)
            ->when($compFilter > 0, fn ($q) => $q->where('m.idMateriaPadre', $compFilter))
            ->select(
                'm.idMateriaPadre as idCompetencia',
                'a.idMateria as idRap',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('m.idMateriaPadre', 'a.idMateria')
            ->get();

        $comps = $rows->pluck('idCompetencia')->unique()->values();
        $report = [];
        $actividadesDetalle = [];

        foreach ($comps as $compId) {
            $raps = DB::table('materia')
                ->where('idMateriaPadre', $compId)
                ->orderBy('id')
                ->get(['id', 'codigo', 'nombreMateria']);

            $actsByRap = $rows->where('idCompetencia', $compId)->keyBy('idRap');
            $conActs = $actsByRap->sum('total');
            if ($conActs <= 0) {
                continue;
            }

            $vacios = [];
            foreach ($raps as $rap) {
                $total = isset($actsByRap[$rap->id]) ? (int) $actsByRap[$rap->id]->total : 0;
                if ($total === 0) {
                    $vacios[] = [
                        'idRap' => (int) $rap->id,
                        'codigo' => $rap->codigo,
                        'nombre' => $rap->nombreMateria,
                    ];
                }
            }

            if ($vacios === []) {
                continue;
            }

            $competencia = Materia::query()->find($compId);
            $conActividades = [];
            foreach ($actsByRap as $idRap => $agg) {
                $m = $raps->firstWhere('id', $idRap);
                $conActividades[] = [
                    'idRap' => (int) $idRap,
                    'codigo' => $m->codigo ?? null,
                    'nombre' => $m->nombreMateria ?? null,
                    'total' => (int) $agg->total,
                ];
            }

            $idsRapConActs = array_keys($actsByRap->all());
            $actsDetalle = DB::table('actividades as a')
                ->whereIn('a.idMateria', $idsRapConActs)
                ->orderBy('a.id')
                ->get(['a.id', 'a.tituloActividad', 'a.idMateria']);

            $fichasMap = [];
            if ($detalle || $this->option('json')) {
                // Reutilizar helper vía un RAP vacío de la competencia
                $primerVacio = (int) ($vacios[0]['idRap'] ?? 0);
                if ($primerVacio > 0) {
                    $diag = DiagnosticoActividadesRapHistoricas::diagnosticarListadoVacio(
                        $primerVacio,
                        null,
                        null,
                        'artisan detalle'
                    );
                    foreach ($diag as $d) {
                        $actividadesDetalle[] = $d;
                        $fichasMap[(int) $d['idActividad']] = $d['fichas'] ?? [];
                    }
                }
            }

            foreach ($actsDetalle as $a) {
                if (! $detalle && ! $this->option('json')) {
                    break;
                }
                // Ya cubierto por diagnosticarListadoVacio; completar si faltó
                if (! isset($fichasMap[(int) $a->id])) {
                    $actividadesDetalle[] = [
                        'idActividad' => (int) $a->id,
                        'tituloActividad' => $a->tituloActividad,
                        'RAP_almacenado' => (int) $a->idMateria,
                        'RAP_esperado_en_este_contexto' => null,
                        'raps_vacios_misma_competencia' => array_column($vacios, 'idRap'),
                        'Competencia' => (int) $compId,
                        'fichas' => [],
                        'Resultado' => DiagnosticoActividadesRapHistoricas::RESULTADO_HISTORICO_RAP_INCORRECTO,
                    ];
                }
            }

            $report[] = [
                'idCompetencia' => (int) $compId,
                'competencia' => $competencia?->nombreMateria,
                'raps_con_actividades' => $conActividades,
                'raps_sin_actividades' => $vacios,
            ];
        }

        $desfasePlaneacion = 0;
        if (Schema::hasTable('planeacionActividades')) {
            $desfasePlaneacion = (int) DB::table('planeacionActividades as pa')
                ->join('actividades as a', 'a.id', '=', 'pa.idActividad')
                ->whereColumn('pa.idMateria', '<>', 'a.idMateria')
                ->count();
        }

        $enCompetencia = (int) DB::table('actividades as a')
            ->join('materia as m', 'm.id', '=', 'a.idMateria')
            ->where(function ($q) {
                $q->whereNull('m.idMateriaPadre')->orWhere('m.idMateriaPadre', 0);
            })
            ->count();

        $payload = [
            'actividades_asociadas_a_competencia' => $enCompetencia,
            'planeacion_desfasada_vs_actividad' => $desfasePlaneacion,
            'competencias_con_raps_vacios_y_hermanos_con_acts' => $report,
            'actividades_detalle' => $actividadesDetalle,
            'nota' => 'RAP_esperado_en_este_contexto = RAP vacío que el instructor abre; RAP_almacenado = donde quedó la actividad. El “RAP correcto” de negocio debe confirmarse al mover.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Actividades en competencia (no RAP): '.$enCompetencia);
        $this->info('Planeación desfasada vs actividad: '.$desfasePlaneacion);
        $this->newLine();

        if ($report === []) {
            $this->info('No se encontraron RAPs vacíos con hermanos que sí tengan actividades.');

            return self::SUCCESS;
        }

        foreach ($report as $block) {
            $this->warn("Competencia {$block['idCompetencia']}: {$block['competencia']}");
            $this->line('  Con actividades:');
            foreach ($block['raps_con_actividades'] as $r) {
                $this->line("    - RAP {$r['idRap']} ({$r['codigo']}): {$r['total']} act(s)");
            }
            $this->line('  Sin actividades (listado vacío si se abre este RAP):');
            foreach ($block['raps_sin_actividades'] as $r) {
                $this->line("    - RAP {$r['idRap']} ({$r['codigo']})");
            }
            $this->newLine();
        }

        if ($detalle && $actividadesDetalle !== []) {
            $this->info('Detalle de actividades en RAP hermano (no aparecen al abrir RAP vacío de la misma competencia):');
            $this->table(
                ['id', 'título', 'RAP_almacenado', 'Competencia', 'fichas'],
                collect($actividadesDetalle)->unique('idActividad')->map(fn ($d) => [
                    $d['idActividad'],
                    mb_substr((string) ($d['tituloActividad'] ?? ''), 0, 40),
                    $d['RAP_almacenado'],
                    $d['Competencia'],
                    implode(',', $d['fichas'] ?? []) ?: '—',
                ])->all()
            );
        }

        $this->comment('El filtro por RAP es correcto. Para simular un RAP vacío: --rap={id}');
        $this->comment('Reasignar: php artisan actividades:reasignar-rap {origen} {destino} --dry-run');

        return self::SUCCESS;
    }
}
