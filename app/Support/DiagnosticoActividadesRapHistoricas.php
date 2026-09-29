<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnóstico de compatibilidad para actividades históricas mal asociadas a un RAP.
 * No altera listados ni filtros: solo detecta y registra.
 */
final class DiagnosticoActividadesRapHistoricas
{
    public const RESULTADO_HISTORICO_RAP_INCORRECTO = 'Actividad histórica asociada a otro RAP de la misma competencia (oculta por filtro estricto).';

    public const RESULTADO_LISTADO_VACIO_SIN_HERMANOS = 'Listado vacío: no hay actividades en este RAP ni en RAP hermanos de la competencia.';

    public const RESULTADO_LISTADO_OK = 'Listado con actividades del RAP solicitado.';

    /**
     * Si el listado del RAP quedó vacío, busca actividades en RAP hermanos (misma competencia)
     * y registra diagnóstico. Nunca incluye esas actividades en el listado.
     *
     * @return list<array<string, mixed>>
     */
    public static function diagnosticarListadoVacio(
        int $idRapSolicitado,
        ?int $idCompany = null,
        ?int $idFicha = null,
        string $contexto = 'listado'
    ): array {
        if ($idRapSolicitado <= 0 || ! Schema::hasTable('actividades') || ! Schema::hasTable('materia')) {
            return [];
        }

        $rap = DB::table('materia')->where('id', $idRapSolicitado)->first(['id', 'codigo', 'nombreMateria', 'idMateriaPadre']);
        if (! $rap) {
            Log::warning('[DiagnosticoRap] RAP solicitado inexistente', [
                'contexto' => $contexto,
                'RAP_solicitado' => $idRapSolicitado,
                'idFicha' => $idFicha,
            ]);

            return [];
        }

        $idCompetencia = (int) ($rap->idMateriaPadre ?? 0);
        if ($idCompetencia <= 0) {
            Log::warning('[DiagnosticoRap] El id solicitado no es un RAP (sin competencia padre)', [
                'contexto' => $contexto,
                'RAP_solicitado' => $idRapSolicitado,
                'Competencia' => null,
                'idFicha' => $idFicha,
                'Resultado' => 'El identificador no es un RAP hijo.',
            ]);

            return [];
        }

        $idsHermanos = DB::table('materia')
            ->where('idMateriaPadre', $idCompetencia)
            ->where('id', '<>', $idRapSolicitado)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($idsHermanos === []) {
            Log::info('[DiagnosticoRap] Listado vacío sin RAP hermanos', [
                'contexto' => $contexto,
                'Clase_idFicha' => $idFicha,
                'Competencia' => $idCompetencia,
                'RAP_solicitado' => $idRapSolicitado,
                'Resultado' => self::RESULTADO_LISTADO_VACIO_SIN_HERMANOS,
            ]);

            return [];
        }

        $query = DB::table('actividades as a')
            ->join('materia as m', 'm.id', '=', 'a.idMateria')
            ->whereIn('a.idMateria', $idsHermanos)
            ->select(
                'a.id as idActividad',
                'a.tituloActividad',
                'a.idMateria as rapAlmacenado',
                'm.codigo as codigoRapAlmacenado',
                'm.nombreMateria as nombreRapAlmacenado',
                'm.idMateriaPadre as idCompetencia'
            )
            ->orderBy('a.id');

        if ($idCompany !== null && $idCompany > 0 && Schema::hasColumn('actividades', 'idCompany')) {
            $query->where('a.idCompany', $idCompany);
        }

        $encontradas = $query->get();
        if ($encontradas->isEmpty()) {
            Log::info('[DiagnosticoRap] Listado vacío; competencia sin actividades en hermanos', [
                'contexto' => $contexto,
                'Clase_idFicha' => $idFicha,
                'Competencia' => $idCompetencia,
                'RAP_solicitado' => $idRapSolicitado,
                'RAP_solicitado_codigo' => $rap->codigo,
                'Resultado' => self::RESULTADO_LISTADO_VACIO_SIN_HERMANOS,
            ]);

            return [];
        }

        $fichasPorActividad = self::fichasRelacionadas($encontradas->pluck('idActividad')->all());

        $diagnosticos = [];
        foreach ($encontradas as $row) {
            $idAct = (int) $row->idActividad;
            $fichas = $fichasPorActividad[$idAct] ?? [];
            $item = [
                'idActividad' => $idAct,
                'tituloActividad' => $row->tituloActividad,
                'RAP_solicitado' => $idRapSolicitado,
                'RAP_solicitado_codigo' => $rap->codigo,
                'RAP_almacenado' => (int) $row->rapAlmacenado,
                'RAP_almacenado_codigo' => $row->codigoRapAlmacenado,
                'RAP_esperado_en_este_contexto' => $idRapSolicitado,
                'Competencia' => $idCompetencia,
                'fichas' => $fichas,
                'Resultado' => self::RESULTADO_HISTORICO_RAP_INCORRECTO,
            ];
            $diagnosticos[] = $item;

            Log::warning('[DiagnosticoRap] Actividad histórica en RAP hermano (oculta por filtro estricto)', [
                'contexto' => $contexto,
                'Clase_idFicha' => $idFicha,
                'Competencia' => $idCompetencia,
                'RAP_solicitado' => $idRapSolicitado,
                'RAP_almacenado' => (int) $row->rapAlmacenado,
                'Actividad' => $idAct,
                'tituloActividad' => $row->tituloActividad,
                'fichas' => $fichas,
                'Resultado' => self::RESULTADO_HISTORICO_RAP_INCORRECTO,
            ]);
        }

        Log::warning('[DiagnosticoRap] Resumen listado vacío con actividades en hermanos', [
            'contexto' => $contexto,
            'Clase_idFicha' => $idFicha,
            'Competencia' => $idCompetencia,
            'RAP_solicitado' => $idRapSolicitado,
            'cantidad_ocultas_por_filtro' => count($diagnosticos),
            'Resultado' => self::RESULTADO_HISTORICO_RAP_INCORRECTO,
        ]);

        return $diagnosticos;
    }

    public static function logMovimientoRap(
        int $idActividad,
        int $idOrigen,
        int $idDestino,
        int $idFicha,
        bool $ok,
        ?string $detalle = null
    ): void {
        Log::info('[DiagnosticoRap] Movimiento de actividad entre RAPs', [
            'Actividad' => $idActividad,
            'Origen' => $idOrigen,
            'Destino' => $idDestino,
            'Clase_idFicha' => $idFicha,
            'Resultado' => $ok
                ? 'RAP actualizado correctamente (actividades.idMateria + planeacionActividades si aplica).'
                : ('Error de movimiento: '.($detalle ?? 'desconocido')),
        ]);
    }

    public static function logCreacionActividad(int $idActividad, int $idMateria, string $origen = 'store'): void
    {
        $materia = DB::table('materia')->where('id', $idMateria)->first(['id', 'idMateriaPadre', 'codigo']);
        $esRap = $materia && (int) ($materia->idMateriaPadre ?? 0) > 0;

        Log::info('[DiagnosticoRap] Creación de actividad', [
            'origen' => $origen,
            'Actividad' => $idActividad,
            'RAP_almacenado' => $idMateria,
            'es_RAP' => $esRap,
            'Competencia' => $esRap ? (int) $materia->idMateriaPadre : null,
            'Resultado' => $esRap
                ? 'actividades.idMateria = RAP actual (correcto).'
                : 'ADVERTENCIA: idMateria no es un RAP (posible competencia).',
        ]);
    }

    /**
     * @param  list<int>  $idsActividad
     * @return array<int, list<int>>
     */
    private static function fichasRelacionadas(array $idsActividad): array
    {
        $map = [];
        foreach ($idsActividad as $id) {
            $map[(int) $id] = [];
        }
        if ($idsActividad === []) {
            return $map;
        }

        if (Schema::hasTable('calificacionActividad')) {
            $tablaMa = Schema::hasTable('matriculaAcademica') ? 'matriculaAcademica' : (Schema::hasTable('matriculaacademica') ? 'matriculaacademica' : null);
            if ($tablaMa && Schema::hasColumn($tablaMa, 'idFicha')) {
                $rows = DB::table('calificacionActividad as ca')
                    ->join($tablaMa.' as ma', 'ca.idAMartriculaAcademica', '=', 'ma.id')
                    ->whereIn('ca.idActividad', $idsActividad)
                    ->whereNotNull('ma.idFicha')
                    ->select('ca.idActividad', 'ma.idFicha')
                    ->distinct()
                    ->get();
                foreach ($rows as $r) {
                    $map[(int) $r->idActividad][] = (int) $r->idFicha;
                }
            }
        }

        if (Schema::hasTable('planeacionActividades') && Schema::hasTable('planeacion') && Schema::hasTable('horarioMateria')) {
            if (Schema::hasColumn('planeacion', 'idContrato') && Schema::hasColumn('horarioMateria', 'idContrato')) {
                $rows = DB::table('planeacionActividades as pa')
                    ->join('planeacion as p', 'p.id', '=', 'pa.idPlaneacion')
                    ->join('horarioMateria as hm', 'hm.idContrato', '=', 'p.idContrato')
                    ->whereIn('pa.idActividad', $idsActividad)
                    ->select('pa.idActividad', 'hm.idFicha')
                    ->distinct()
                    ->get();
                foreach ($rows as $r) {
                    $idA = (int) $r->idActividad;
                    $idF = (int) $r->idFicha;
                    if ($idF > 0 && ! in_array($idF, $map[$idA], true)) {
                        $map[$idA][] = $idF;
                    }
                }
            }
        }

        foreach ($map as $id => $fichas) {
            $map[$id] = array_values(array_unique($fichas));
        }

        return $map;
    }
}
