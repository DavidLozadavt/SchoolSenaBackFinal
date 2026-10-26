<?php

namespace App\Services\ambiente_virtual;

use App\Models\Materia;
use App\Support\MaterialCompetenciaReglas;
use App\Util\KeyUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RAP accesibles según asignaciones reales:
 * instructor → horarioMateria / asignacionSesion (contrato activo)
 * aprendiz → fichas de matrícula vigente + horarios de esas fichas
 */
class RapAccessService
{
    /** @var list<string> */
    private const ESTADOS_MATRICULA_VIGENTE = ['ACTIVO', 'EN CURSO', 'CURSANDO', 'MATRICULADO', 'EN FORMACION'];

    public function usuarioPuedeAdministrar(): bool
    {
        if ($this->tienePermiso('AULA_VIRTUAL_INSTRUCTOR') || $this->tienePermiso('GESTION_USUARIO')) {
            return true;
        }

        try {
            return KeyUtil::hasRole('INSTRUCTOR');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function usuarioEsAprendiz(): bool
    {
        if ($this->tienePermiso('AULA_VIRTUAL_APRENDIZ')) {
            return true;
        }

        try {
            return KeyUtil::hasRole('APRENDIZ') || KeyUtil::hasRole('ESTUDIANTE');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<int>
     */
    public function idsRapAccesibles(): array
    {
        if ($this->usuarioPuedeAdministrar()) {
            return $this->idsRapInstructor();
        }
        if ($this->usuarioEsAprendiz()) {
            return $this->idsRapAprendiz();
        }

        return [];
    }

    public function usuarioPuedeAccederRap(int $idRap): bool
    {
        if ($idRap <= 0) {
            return false;
        }

        return in_array($idRap, $this->idsRapAccesibles(), true);
    }

    /**
     * @return list<int>
     */
    public function idsCompetenciaAccesibles(): array
    {
        $idsRap = $this->idsRapAccesibles();
        if ($idsRap === []) {
            return [];
        }

        $ids = DB::table('materia')
            ->whereIn('id', $idsRap)
            ->whereNotNull('idMateriaPadre')
            ->where('idMateriaPadre', '>', 0)
            ->pluck('idMateriaPadre')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('materia')
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('idMateriaPadre')->orWhere('idMateriaPadre', '<=', 0);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function usuarioPuedeAccederCompetencia(int $idCompetencia): bool
    {
        if ($idCompetencia <= 0) {
            return false;
        }

        return in_array($idCompetencia, $this->idsCompetenciaAccesibles(), true);
    }

    /**
     * RAP hijos reales de una competencia (materia.idMateriaPadre = competencia).
     *
     * @return list<int>
     */
    public function idsRapDeCompetencia(int $idCompetencia): array
    {
        if ($idCompetencia <= 0 || ! Schema::hasTable('materia')) {
            return [];
        }

        return DB::table('materia')
            ->where('idMateriaPadre', $idCompetencia)
            ->whereNotNull('idMateriaPadre')
            ->where('idMateriaPadre', '>', 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function competenciasAccesibles(): array
    {
        $ids = $this->idsCompetenciaAccesibles();
        if ($ids === []) {
            return [];
        }

        $rows = Materia::query()
            ->from('materia')
            ->whereIn('materia.id', $ids)
            ->where(function ($q) {
                $q->whereNull('materia.idMateriaPadre')->orWhere('materia.idMateriaPadre', '<=', 0);
            })
            ->orderBy('materia.nombreMateria')
            ->get([
                'materia.id',
                'materia.nombreMateria',
                'materia.codigo',
                'materia.idMateriaPadre',
            ]);

        $conteoRaps = DB::table('materia')
            ->whereIn('idMateriaPadre', $ids)
            ->select('idMateriaPadre', DB::raw('COUNT(*) as total'))
            ->groupBy('idMateriaPadre')
            ->pluck('total', 'idMateriaPadre');

        return $rows->map(static function ($r) use ($conteoRaps) {
            $id = (int) $r->id;

            return [
                'id' => $id,
                'nombreMateria' => $r->nombreMateria,
                'codigo' => $r->codigo,
                'totalRaps' => (int) ($conteoRaps[$id] ?? $conteoRaps[(string) $id] ?? 0),
            ];
        })->sortBy([
            fn ($r) => (string) ($r['codigo'] ?? ''),
            fn ($r) => (string) ($r['nombreMateria'] ?? ''),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rapsAccesibles(): array
    {
        $ids = $this->idsRapAccesibles();
        if ($ids === []) {
            return [];
        }

        $query = Materia::query()
            ->from('materia')
            ->whereIn('materia.id', $ids)
            ->whereNotNull('materia.idMateriaPadre')
            ->where('materia.idMateriaPadre', '>', 0);

        $rows = $query
            ->leftJoin('materia as comp', 'materia.idMateriaPadre', '=', 'comp.id')
            ->orderBy('materia.nombreMateria')
            ->get([
                'materia.id',
                'materia.nombreMateria',
                'materia.codigo',
                'materia.idMateriaPadre',
                'comp.nombreMateria as competenciaNombre',
                'comp.codigo as competenciaCodigo',
            ]);

        $mapped = $rows->map(static function ($r) {
            $numero = Materia::numeroOrdenRap($r->codigo ?? null, $r->nombreMateria ?? null);

            return [
                'id' => (int) $r->id,
                'nombreMateria' => $r->nombreMateria,
                'codigo' => $r->codigo,
                'idCompetencia' => $r->idMateriaPadre ? (int) $r->idMateriaPadre : null,
                'competenciaNombre' => $r->competenciaNombre,
                'competenciaCodigo' => $r->competenciaCodigo,
                'numeroRap' => $numero === PHP_INT_MAX ? null : $numero,
            ];
        })->sortBy([
            fn ($r) => $r['numeroRap'] ?? PHP_INT_MAX,
            fn ($r) => (string) ($r['codigo'] ?? ''),
            fn ($r) => (string) ($r['nombreMateria'] ?? ''),
        ])->values()->all();

        return $mapped;
    }

    /**
     * IDs de ficha del instructor compatibles con el RAP (todas, no la primera).
     *
     * @return list<int>
     */
    public function idsFichasHorarioDeRap(int $idRap): array
    {
        return array_values(array_map(
            static fn (array $ficha): int => (int) $ficha['id'],
            $this->fichasHorarioDeRap($idRap)
        ));
    }

    /**
     * Todas las fichas del instructor (misma fuente que Mis clases / clases-asignadas).
     *
     * @return list<array{id:int,codigo:?string,idHorarioMateria:?int}>
     */
    public function fichasInstructor(): array
    {
        $filas = $this->filasHorarioInstructor();
        $porFicha = [];
        foreach ($filas as $row) {
            $idFicha = (int) ($row->idFicha ?? 0);
            if ($idFicha <= 0 || isset($porFicha[$idFicha])) {
                continue;
            }
            $porFicha[$idFicha] = [
                'id' => $idFicha,
                'codigo' => $row->codigo !== null ? (string) $row->codigo : null,
                'idHorarioMateria' => isset($row->idHorarioMateria) ? (int) $row->idHorarioMateria : null,
            ];
        }

        return array_values($porFicha);
    }

    /**
     * Fichas del instructor compatibles con el RAP de la actividad.
     * Universo: las mismas fichas de Mis clases. Filtro: el RAP está en el horario
     * de esa ficha, o el horario está en la competencia padre (se expanden sus RAP).
     * No incluye fichas que solo tienen un RAP hermano.
     *
     * @return list<array{id:int,codigo:?string,idHorarioMateria:?int}>
     */
    public function fichasHorarioDeRap(int $idRap): array
    {
        if ($idRap <= 0 || ! $this->usuarioPuedeAdministrar() || ! $this->usuarioPuedeAccederRap($idRap)) {
            return [];
        }

        $instructor = $this->fichasInstructor();
        if ($instructor === []) {
            return [];
        }

        $idsFicha = array_map(static fn (array $f): int => (int) $f['id'], $instructor);
        $filas = $this->filasHorarioDeFichas($idsFicha);
        $porFicha = [];
        foreach ($instructor as $ficha) {
            $porFicha[(int) $ficha['id']] = $ficha;
            $porFicha[(int) $ficha['id']]['filas'] = [];
        }
        foreach ($filas as $row) {
            $idFicha = (int) ($row->idFicha ?? 0);
            if ($idFicha <= 0 || ! isset($porFicha[$idFicha])) {
                continue;
            }
            if (($porFicha[$idFicha]['codigo'] ?? null) === null && isset($row->codigo)) {
                $porFicha[$idFicha]['codigo'] = $row->codigo !== null ? (string) $row->codigo : null;
            }
            $porFicha[$idFicha]['filas'][] = $row;
        }

        $compatibles = [];
        foreach ($porFicha as $idFicha => $info) {
            $idsRapFicha = $this->expandirIdsRap($info['filas'] ?? []);
            if (! in_array($idRap, $idsRapFicha, true)) {
                continue;
            }
            $compatibles[] = [
                'id' => (int) $idFicha,
                'codigo' => $info['codigo'] ?? null,
                'idHorarioMateria' => $info['idHorarioMateria'] ?? null,
            ];
        }

        usort($compatibles, static fn ($a, $b) => strcmp((string) ($a['codigo'] ?? $a['id']), (string) ($b['codigo'] ?? $b['id'])));

        return $compatibles;
    }

    /**
     * Filas de horario del contrato activo (y reemplazos en asignacionSesion).
     * Misma pertenencia que FichaController::clasesAsignadasInstructor, sin exigir idDia.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function filasHorarioInstructor()
    {
        $idContrato = $this->idContratoActivo();
        if (
            $idContrato <= 0
            || ! Schema::hasTable('horarioMateria')
            || ! Schema::hasTable('gradoMateria')
            || ! Schema::hasTable('ficha')
            || ! Schema::hasTable('materia')
        ) {
            return collect();
        }

        $qb = DB::table('horarioMateria as hm')
            ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
            ->join('materia as m', 'gm.idMateria', '=', 'm.id')
            ->join('ficha as f', 'hm.idFicha', '=', 'f.id');

        $tieneAsignacionSesion = Schema::hasTable('asignacionSesion');
        if ($tieneAsignacionSesion) {
            $qb->leftJoin('asignacionSesion as asig', function ($join) use ($idContrato) {
                $join->on('hm.id', '=', 'asig.idHorarioMateria')
                    ->where('asig.idContrato', '=', $idContrato);
            });
        }

        $qb->where(function ($query) use ($idContrato, $tieneAsignacionSesion) {
            $query->where('hm.idContrato', $idContrato);
            if ($tieneAsignacionSesion) {
                $query->orWhereNotNull('asig.id');
            }
        });

        return $qb->select([
            'f.id as idFicha',
            'f.codigo',
            'hm.id as idHorarioMateria',
            'm.id',
            'm.idMateriaPadre',
        ])
            ->orderBy('f.codigo')
            ->orderByDesc('hm.id')
            ->get();
    }

    /**
     * RAP del catálogo de Gestión: union del horario completo de las fichas del instructor
     * (misma semántica que la clase / el aprendiz de esas fichas), no solo las franjas
     * del contrato activo. Así no se pierden RAP/competencias que existen en la ficha
     * pero están en un horario de otro contrato o sin idContrato.
     *
     * @return list<int>
     */
    private function idsRapInstructor(): array
    {
        $filasPropias = $this->filasHorarioInstructor();
        if ($filasPropias->isEmpty()) {
            return [];
        }

        $idsFicha = $filasPropias
            ->pluck('idFicha')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($idsFicha === []) {
            return $this->idsRapDesdeFilasHorario($filasPropias, true);
        }

        return $this->idsRapDesdeFilasHorario($this->filasHorarioDeFichas($idsFicha), true);
    }

    /**
     * Horario académico completo de las fichas (cualquier contrato).
     * Equivale a lo que ve un aprendiz matriculado en esas fichas / rapsHorarioFicha.
     *
     * @param  list<int>  $idsFicha
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function filasHorarioDeFichas(array $idsFicha)
    {
        $idsFicha = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $idsFicha),
            static fn ($id) => $id > 0
        )));
        if (
            $idsFicha === []
            || ! Schema::hasTable('horarioMateria')
            || ! Schema::hasTable('gradoMateria')
            || ! Schema::hasTable('materia')
        ) {
            return collect();
        }

        $qb = DB::table('horarioMateria as hm')
            ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
            ->join('materia as m', 'gm.idMateria', '=', 'm.id')
            ->whereIn('hm.idFicha', $idsFicha)
            ->select('m.id', 'm.idMateriaPadre', 'hm.idFicha');
        if (Schema::hasTable('ficha')) {
            $qb->leftJoin('ficha as f', 'hm.idFicha', '=', 'f.id')
                ->addSelect('f.codigo');
        }

        return $qb->distinct()->get();
    }

    /**
     * @return list<int>
     */
    private function idsRapAprendiz(): array
    {
        $idsFicha = $this->idsFichaAprendiz();
        if ($idsFicha === [] || ! Schema::hasTable('horarioMateria') || ! Schema::hasTable('gradoMateria')) {
            return [];
        }

        $rows = DB::table('horarioMateria as hm')
            ->join('gradoMateria as gm', 'hm.idGradoMateria', '=', 'gm.id')
            ->join('materia as m', 'gm.idMateria', '=', 'm.id')
            ->whereIn('hm.idFicha', $idsFicha)
            ->select('m.id', 'm.idMateriaPadre')
            ->distinct()
            ->get();

        return $this->idsRapDesdeFilasHorario($rows, false);
    }

    /**
     * RAP presentes en el horario académico de las fichas dadas.
     *
     * @param  list<int>  $idsFicha
     * @return list<int>
     */
    public function idsRapDeFichas(array $idsFicha, bool $completarHermanos = true): array
    {
        return $this->idsRapDesdeFilasHorario($this->filasHorarioDeFichas($idsFicha), $completarHermanos);
    }

    /**
     * RAP derivados de filas de horario (materia.id + idMateriaPadre).
     * Expande horarios sobre competencia. Opcionalmente completa hermanos
     * de competencias ya alcanzadas (instructor / filtros de catálogo).
     *
     * @param  \Illuminate\Support\Collection<int, object>|iterable<int, object>  $filas
     * @return list<int>
     */
    public function idsRapDesdeFilasHorario($filas, bool $completarHermanos = false): array
    {
        $ids = $this->expandirIdsRap($filas);
        if ($completarHermanos) {
            $ids = $this->completarRapsDeCompetenciasAlcanzadas($ids);
        }

        return $ids;
    }

    /**
     * Si ya se alcanzó una competencia (horario en ella o en alguno de sus RAP),
     * incluye todos los RAP hijos. No abre competencias ajenas.
     *
     * @param  list<int>  $idsRap
     * @return list<int>
     */
    public function completarRapsDeCompetenciasAlcanzadas(array $idsRap): array
    {
        $idsRap = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $idsRap),
            static fn ($id) => $id > 0
        )));
        if ($idsRap === [] || ! Schema::hasTable('materia')) {
            return $idsRap;
        }

        $idsCompetencia = DB::table('materia')
            ->whereIn('id', $idsRap)
            ->whereNotNull('idMateriaPadre')
            ->where('idMateriaPadre', '>', 0)
            ->pluck('idMateriaPadre')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($idsCompetencia === []) {
            sort($idsRap);

            return $idsRap;
        }

        $hijos = DB::table('materia')
            ->whereIn('idMateriaPadre', $idsCompetencia)
            ->whereNotNull('idMateriaPadre')
            ->where('idMateriaPadre', '>', 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->all();

        $ids = array_values(array_unique(array_merge($idsRap, $hijos)));
        sort($ids);

        return $ids;
    }

    /**
     * RAP directo (materia con padre) + RAP hijos si el horario está en la competencia.
     * Misma semántica que HorarioMateriaController al agrupar horas de competencia/RAP.
     *
     * @param  \Illuminate\Support\Collection<int, object>|iterable<int, object>  $filas
     * @return list<int>
     */
    private function expandirIdsRap($filas): array
    {
        $idsRap = [];
        $idsCompetencia = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila->id ?? 0);
            $padre = isset($fila->idMateriaPadre) ? (int) $fila->idMateriaPadre : 0;
            if ($id <= 0) {
                continue;
            }
            if (MaterialCompetenciaReglas::esRap($padre > 0 ? $padre : null)) {
                $idsRap[] = $id;
            } else {
                $idsCompetencia[] = $id;
            }
        }

        if ($idsCompetencia !== [] && Schema::hasTable('materia')) {
            $hijos = DB::table('materia')
                ->whereIn('idMateriaPadre', array_values(array_unique($idsCompetencia)))
                ->whereNotNull('idMateriaPadre')
                ->where('idMateriaPadre', '>', 0)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $idsRap = array_merge($idsRap, $hijos);
        }

        $idsRap = array_values(array_unique(array_filter($idsRap, fn ($id) => $id > 0)));
        sort($idsRap);

        return $idsRap;
    }

    /**
     * @return list<int>
     */
    private function idsFichaAprendiz(): array
    {
        $user = KeyUtil::user();
        $idPersona = $user?->idpersona ? (int) $user->idpersona : 0;
        if ($idPersona <= 0 || ! Schema::hasTable('matricula')) {
            return [];
        }

        $idsFichas = DB::table('matricula as m')
            ->where('m.idPersona', $idPersona)
            ->whereIn(DB::raw('UPPER(TRIM(m.estado))'), self::ESTADOS_MATRICULA_VIGENTE)
            ->whereNotNull('m.idFicha')
            ->distinct()
            ->pluck('m.idFicha')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($idsFichas !== []) {
            return $idsFichas;
        }

        $tablaMa = Schema::hasTable('matriculaAcademica')
            ? 'matriculaAcademica'
            : (Schema::hasTable('matriculaacademica') ? 'matriculaacademica' : null);
        if (! $tablaMa) {
            return [];
        }

        return DB::table($tablaMa.' as ma')
            ->join('matricula as m', 'ma.idMatricula', '=', 'm.id')
            ->where('m.idPersona', $idPersona)
            ->whereIn(DB::raw('UPPER(TRIM(m.estado))'), self::ESTADOS_MATRICULA_VIGENTE)
            ->whereNotNull('ma.idFicha')
            ->distinct()
            ->pluck('ma.idFicha')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function idContratoActivo(): int
    {
        try {
            $contrato = KeyUtil::lastContractActive();

            return $contrato && $contrato->id ? (int) $contrato->id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function tienePermiso(string $nombre): bool
    {
        return in_array($nombre, $this->nombresPermisosJwt(), true);
    }

    /**
     * JWT guarda una lista de nombres; KeyUtil::permissions() usa keys() y
     * devolvería 0,1,2… si el payload ya es un array indexado.
     *
     * @return list<string>
     */
    private function nombresPermisosJwt(): array
    {
        try {
            $token = \Tymon\JWTAuth\Facades\JWTAuth::getToken();
            $perms = \Tymon\JWTAuth\Facades\JWTAuth::getPayload($token)->get('permissions');
            if ($perms instanceof \Illuminate\Support\Collection) {
                $perms = $perms->all();
            }
            if (! is_array($perms) || $perms === []) {
                return [];
            }
            $isAssoc = array_keys($perms) !== range(0, count($perms) - 1);
            $names = $isAssoc ? array_keys($perms) : array_values($perms);

            return array_values(array_filter(array_map(
                fn ($v) => is_string($v) ? $v : (is_scalar($v) ? (string) $v : null),
                $names
            )));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
