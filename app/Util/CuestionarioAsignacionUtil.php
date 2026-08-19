<?php

namespace App\Util;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subconjunto de preguntas por asignación de cuestionario.
 * Usa filas en respuestaCuestionarios con puntaje = -1 como marcadores (sin respuesta del aprendiz).
 * No modifica el banco original de preguntas.
 */
class CuestionarioAsignacionUtil
{
    public const PUNTAJE_MARCADOR = -1;

    public static function tablaRespuestaCuestionarios(): ?string
    {
        if (Schema::hasTable('respuestaCuestionarios')) {
            return 'respuestaCuestionarios';
        }
        if (Schema::hasTable('respuesta_cuestionarios')) {
            return 'respuesta_cuestionarios';
        }

        return null;
    }

    public static function esMarcador(object $row): bool
    {
        $puntaje = isset($row->puntaje) ? (float) $row->puntaje : null;

        return $puntaje !== null
            && abs($puntaje - self::PUNTAJE_MARCADOR) < 0.0001
            && empty($row->idRespuesta)
            && trim((string) ($row->respuesta ?? '')) === '';
    }

    /**
     * @return int[]|null null = banco completo (sin marcadores)
     */
    public static function idsParaCalificacion(int $idCalificacion): ?array
    {
        $tbl = self::tablaRespuestaCuestionarios();
        if (!$tbl) {
            return null;
        }

        $ids = DB::table($tbl)
            ->where('idCalificacion', $idCalificacion)
            ->where('puntaje', self::PUNTAJE_MARCADOR)
            ->pluck('idPregunta');

        if ($ids->isEmpty()) {
            return null;
        }

        return $ids->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * @param int[] $idsPreguntas
     */
    public static function guardarMarcadores(int $idCalificacion, array $idsPreguntas): void
    {
        $tbl = self::tablaRespuestaCuestionarios();
        if (!$tbl || empty($idsPreguntas)) {
            return;
        }

        foreach (array_unique(array_map('intval', $idsPreguntas)) as $idPregunta) {
            if ($idPregunta <= 0) {
                continue;
            }
            $existe = DB::table($tbl)
                ->where('idCalificacion', $idCalificacion)
                ->where('idPregunta', $idPregunta)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table($tbl)->insert([
                'idCalificacion' => $idCalificacion,
                'idPregunta' => $idPregunta,
                'puntaje' => self::PUNTAJE_MARCADOR,
                'calificado' => false,
            ]);
        }
    }

    public static function copiarMarcadoresDesdeHermanoGrupo(int $idActividad, int $idGrupo, int $idCalificacionNueva): void
    {
        $tbl = self::tablaRespuestaCuestionarios();
        if (!$tbl || !Schema::hasTable('calificacionActividad')) {
            return;
        }

        $idHermano = DB::table('calificacionActividad as ca')
            ->join($tbl . ' as rc', 'rc.idCalificacion', '=', 'ca.id')
            ->where('ca.idActividad', $idActividad)
            ->where('ca.idGrupo', $idGrupo)
            ->where('rc.puntaje', self::PUNTAJE_MARCADOR)
            ->where('ca.id', '!=', $idCalificacionNueva)
            ->orderByDesc('ca.id')
            ->value('ca.id');

        if (!$idHermano) {
            return;
        }

        $ids = self::idsParaCalificacion((int) $idHermano);
        if ($ids !== null) {
            self::guardarMarcadores($idCalificacionNueva, $ids);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $preguntas
     * @return array<int, array<string, mixed>>
     */
    public static function filtrarPreguntasPayload(array $preguntas, int $idCalificacion, ?int $idActividad = null): array
    {
        $ids = self::idsParaCalificacion($idCalificacion);
        if ($ids !== null) {
            $set = array_flip($ids);
            $preguntas = array_values(array_filter($preguntas, fn ($p) => isset($set[(int) ($p['id'] ?? 0)])));
        }

        if ($idActividad !== null) {
            return self::ordenarPreguntasYOpciones($preguntas, $idCalificacion, $idActividad);
        }

        return $preguntas;
    }

    /**
     * Orden estable por intento (idCalificacion), distinto entre aprendices.
     * No usa random() por request: la semilla deriva de IDs ya persistidos.
     *
     * @param array<int, array<string, mixed>> $preguntas
     * @return array<int, array<string, mixed>>
     */
    public static function ordenarPreguntasYOpciones(array $preguntas, int $idCalificacion, int $idActividad): array
    {
        $preguntas = self::ordenarPorSemilla(
            array_values($preguntas),
            "q:{$idCalificacion}:{$idActividad}",
            fn ($p) => (int) (is_object($p) ? ($p->id ?? 0) : ($p['id'] ?? 0))
        );

        foreach ($preguntas as &$p) {
            $idPregunta = (int) (is_object($p) ? ($p->id ?? 0) : ($p['id'] ?? 0));
            $seedOpciones = "o:{$idCalificacion}:{$idPregunta}";

            if (is_array($p) && !empty($p['respuestas']) && is_array($p['respuestas'])) {
                $p['respuestas'] = self::ordenarPorSemilla(
                    array_values($p['respuestas']),
                    $seedOpciones,
                    fn ($r) => (int) ($r['id'] ?? 0)
                );
            }
            if (is_array($p) && !empty($p['opciones']) && is_array($p['opciones'])) {
                $p['opciones'] = self::ordenarPorSemilla(
                    array_values($p['opciones']),
                    $seedOpciones,
                    fn ($r) => (int) ($r['id'] ?? 0)
                );
            }
        }
        unset($p);

        return array_values($preguntas);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>|\Illuminate\Database\Eloquent\Collection<int, mixed>|array<int, mixed>  $preguntas
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public static function ordenarColeccionPreguntas($preguntas, int $idCalificacion, int $idActividad)
    {
        $items = $preguntas instanceof \Illuminate\Support\Collection
            ? $preguntas->values()->all()
            : array_values($preguntas);

        $items = self::ordenarPorSemilla(
            $items,
            "q:{$idCalificacion}:{$idActividad}",
            fn ($p) => (int) (is_object($p) ? $p->id : ($p['id'] ?? 0))
        );

        foreach ($items as $p) {
            if (!is_object($p) || !method_exists($p, 'relationLoaded') || !$p->relationLoaded('respuestas') || !$p->respuestas) {
                continue;
            }
            $ops = $p->respuestas instanceof \Illuminate\Support\Collection
                ? $p->respuestas->all()
                : (array) $p->respuestas;
            $ops = self::ordenarPorSemilla(
                $ops,
                "o:{$idCalificacion}:" . (int) $p->id,
                fn ($r) => (int) (is_object($r) ? $r->id : ($r['id'] ?? 0))
            );
            $p->setRelation('respuestas', collect(array_values($ops)));
        }

        return collect(array_values($items));
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    private static function ordenarPorSemilla(array $items, string $seed, callable $getId): array
    {
        usort($items, function ($a, $b) use ($seed, $getId) {
            $idA = $getId($a);
            $idB = $getId($b);
            $cmp = strcmp(hash('sha256', $seed . ':' . $idA), hash('sha256', $seed . ':' . $idB));
            if ($cmp !== 0) {
                return $cmp;
            }

            return $idA <=> $idB;
        });

        return array_values($items);
    }
}
