<?php

namespace App\Console\Commands;

use App\Models\Actividad;
use App\Models\Materia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reasigna actividades de un RAP a otro (misma competencia recomendada).
 * No relaja filtros de listado: corrige la FK actividades.idMateria y planeación.
 */
class ReasignarActividadesRap extends Command
{
    protected $signature = 'actividades:reasignar-rap
                            {origen : idMateria (RAP) actual de las actividades}
                            {destino : idMateria (RAP) destino}
                            {--dry-run : Solo muestra qué se actualizaría}
                            {--ids= : Lista de id de actividades separada por comas (opcional; si se omite, todas las del origen)}';

    protected $description = 'Mueve actividades de un RAP a otro actualizando idMateria y planeacionActividades';

    public function handle(): int
    {
        $idOrigen = (int) $this->argument('origen');
        $idDestino = (int) $this->argument('destino');
        $dryRun = (bool) $this->option('dry-run');

        if ($idOrigen <= 0 || $idDestino <= 0 || $idOrigen === $idDestino) {
            $this->error('Origen y destino deben ser RAP distintos y válidos.');

            return self::FAILURE;
        }

        $origen = Materia::query()->find($idOrigen);
        $destino = Materia::query()->find($idDestino);
        if (! $origen || ! $destino) {
            $this->error('Origen o destino no existen en materia.');

            return self::FAILURE;
        }
        if (! $origen->esRap() || ! $destino->esRap()) {
            $this->error('Origen y destino deben ser RAP (hijos de competencia), no competencias.');

            return self::FAILURE;
        }

        if ((int) $origen->idMateriaPadre !== (int) $destino->idMateriaPadre) {
            if (! $this->confirm('Origen y destino pertenecen a competencias distintas. ¿Continuar de todos modos?', false)) {
                $this->warn('Cancelado.');

                return self::FAILURE;
            }
        }

        $query = Actividad::query()->where('idMateria', $idOrigen);
        $idsOpt = trim((string) $this->option('ids'));
        if ($idsOpt !== '') {
            $ids = collect(explode(',', $idsOpt))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();
            if ($ids === []) {
                $this->error('La opción --ids no contiene identificadores válidos.');

                return self::FAILURE;
            }
            $query->whereIn('id', $ids);
        }

        $actividades = $query->orderBy('id')->get(['id', 'tituloActividad', 'idMateria']);
        if ($actividades->isEmpty()) {
            $this->warn('No hay actividades que coincidan con el criterio.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Reasignar '.$actividades->count().' actividad(es):');
        $this->line("  Origen  RAP {$idOrigen}: {$origen->codigo} — {$origen->nombreMateria}");
        $this->line("  Destino RAP {$idDestino}: {$destino->codigo} — {$destino->nombreMateria}");
        foreach ($actividades as $a) {
            $this->line("  - #{$a->id} {$a->tituloActividad}");
        }

        if ($dryRun) {
            $this->comment('Nada modificado. Quite --dry-run para aplicar.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar la reasignación?', true)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $idsAct = $actividades->pluck('id')->all();

        DB::transaction(function () use ($idsAct, $idDestino) {
            Actividad::query()->whereIn('id', $idsAct)->update(['idMateria' => $idDestino]);

            if (Schema::hasTable('planeacionActividades')) {
                DB::table('planeacionActividades')
                    ->whereIn('idActividad', $idsAct)
                    ->update(['idMateria' => $idDestino]);
            }
        });

        $this->info('Listo. '.$actividades->count().' actividad(es) ahora tienen idMateria='.$idDestino.'.');
        $this->comment('Los listados siguen filtrando estrictamente por RAP.');

        return self::SUCCESS;
    }
}
