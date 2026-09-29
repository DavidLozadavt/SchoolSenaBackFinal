<?php

namespace App\Util;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Subconjunto de preguntas por intento de cuestionario.
 * - El instructor configura solo la CANTIDAD (N) desde el banco (M).
 * - Cada aprendiz/intento recibe N IDs únicos vía marcadores (puntaje = -1).
 * - El banco original (tabla preguntas) no se modifica.
 */
class CuestionarioAsignacionUtil
{
    public const PUNTAJE_MARCADOR = -1;

    public static function tablaRespuestaCuestionarios(): ?string
    {
        foreach (['respuestaCuestionarios', 'respuestasCuestionarios', 'respuesta_cuestionarios'] as $tabla) {
            if (Schema::hasTable($tabla)) {
                return $tabla;
            }
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

    /**
     * Elimina respuestas y marcadores del intento (para regenerar en un reintento).
     */
    public static function limpiarRespuestasYMarcadores(int $idCalificacion): void
    {
        $tbl = self::tablaRespuestaCuestionarios();
        if (!$tbl) {
            return;
        }
        DB::table($tbl)->where('idCalificacion', $idCalificacion)->delete();
    }

    /**
     * @return int[] IDs del banco completo de la actividad (orden estable por id).
     */
    public static function idsBancoActividad(int $idActividad): array
    {
        if (!Schema::hasTable('preguntas')) {
            return [];
        }

        return DB::table('preguntas')
            ->where('idActividad', $idActividad)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * Selecciona N preguntas únicas del banco M de forma determinística.
     * Varía por idCalificacion (aprendiz/asignación) e intentoKey (reintento).
     * Estable para la misma semilla; sin random() por request.
     *
     * @return int[] exactamente min(N, M) IDs distintos
     */
    public static function seleccionarNPreguntasDelBanco(
        int $idActividad,
        int $idCalificacion,
        int $cantidad,
        int $intentoKey = 0
    ): array {
        $banco = self::idsBancoActividad($idActividad);
        $m = count($banco);
        if ($m === 0) {
            return [];
        }
        $n = min(max(1, $cantidad), $m);
        // Semilla distinta a la de presentación (`orderQ:`) para que selección y orden sean independientes.
        $seed = "pick:{$idCalificacion}:{$idActividad}:k{$intentoKey}";
        $items = array_map(static fn (int $id) => ['id' => $id], $banco);
        $ordenados = self::ordenarPorSemilla($items, $seed, static fn ($p) => (int) ($p['id'] ?? 0));

        return array_slice(array_map(static fn ($p) => (int) $p['id'], $ordenados), 0, $n);
    }

    /**
     * Sustituye el subconjunto del intento (borra respuestas previas + escribe marcadores).
     *
     * @param int[] $idsPreguntas
     */
    public static function reemplazarMarcadoresIntento(int $idCalificacion, array $idsPreguntas): void
    {
        self::limpiarRespuestasYMarcadores($idCalificacion);
        self::guardarMarcadores($idCalificacion, $idsPreguntas);
    }

    public static function copiarMarcadoresDesdeHermanoGrupo(int $idActividad, int $idGrupo, int $idCalificacionNueva): void
    {
        $tbl = self::tablaRespuestaCuestionarios();
        if (!$tbl || !Schema::hasTable('calificacionActividad')) {
            return;
        }

        // Un grupo puede estar vacío al asignar. Su configuración se conserva en el
        // almacenamiento privado existente, vinculada al ID de asignacionActividadGrupo.
        $asignacionGrupo = Schema::hasTable('asignacionActividadGrupo')
            ? DB::table('asignacionActividadGrupo')->where('idActividad', $idActividad)->where('idGrupo', $idGrupo)->first()
            : null;
        $configGrupo = $asignacionGrupo ? self::leerConfiguracionGrupo((int) $asignacionGrupo->id) : null;
        if ($configGrupo !== null) {
            $n = $configGrupo['cantidadPreguntas'];
            $ids = self::seleccionarNPreguntasDelBanco($idActividad, $idCalificacionNueva, $n, 0);
            self::guardarMarcadores($idCalificacionNueva, $ids);
            self::persistirMetaAsignacion($idCalificacionNueva, $n, 0, false, null, null, null, false, $configGrupo['tiempoCuestionario']);

            return;
        }

        $hermano = DB::table('calificacionActividad as ca')
            ->where('ca.idActividad', $idActividad)
            ->where('ca.idGrupo', $idGrupo)
            ->where('ca.id', '!=', $idCalificacionNueva)
            ->orderByDesc('ca.id')
            ->first();

        if (!$hermano) {
            return;
        }

        $meta = self::leerMetaIntentoCuestionario(
            isset($hermano->calificacionEstandart) ? (string) $hermano->calificacionEstandart : null
        );
        $n = $meta['cantidad'] ?? null;
        if ($n === null || $n < 1) {
            // Legacy: copiar IDs fijos del hermano
            $ids = self::idsParaCalificacion((int) $hermano->id);
            if ($ids !== null) {
                self::guardarMarcadores($idCalificacionNueva, $ids);
            }

            return;
        }

        // Misma cantidad, subconjunto propio del nuevo aprendiz
        $ids = self::seleccionarNPreguntasDelBanco($idActividad, $idCalificacionNueva, (int) $n, 0);
        self::guardarMarcadores($idCalificacionNueva, $ids);
        self::persistirMetaAsignacion($idCalificacionNueva, (int) $n, 0, false, null, null, null, false, $meta['tiempoMinutos']);
    }

    /**
     * Prepara/regenera el subconjunto al abrir para responder.
     * - Primer intento: si no hay marcadores y hay cantidad, genera k=0.
     * - Reintento permitido (ya finalizó): regenera k+1 una sola vez (flag open).
     * - Revisión / intento en curso: no altera.
     */
    public static function asegurarSubconjuntoParaResponder(
        int $idCalificacion,
        int $idActividad,
        bool $puedeIniciarNuevoIntento
    ): void {
        if (!Schema::hasTable('calificacionActividad')) {
            return;
        }
        $ca = DB::table('calificacionActividad')->where('id', $idCalificacion)->first();
        if (!$ca) {
            return;
        }

        $meta = self::leerMetaIntentoCuestionario(
            isset($ca->calificacionEstandart) ? (string) $ca->calificacionEstandart : null
        );
        $n = $meta['cantidad'] ?? null;
        if ($n === null || $n < 1) {
            return; // legacy sin cantidad configurada
        }

        $idsActuales = self::idsParaCalificacion($idCalificacion);
        $finalizado = !empty($ca->fechaCalificacion);
        $open = !empty($meta['open']);
        $k = (int) ($meta['intento'] ?? 0);

        if (!$finalizado && $idsActuales !== null) {
            return;
        }
        if ($finalizado && $open && $idsActuales !== null) {
            return;
        }

        if ($finalizado && $puedeIniciarNuevoIntento) {
            $kNuevo = $k + 1;
            $ids = self::seleccionarNPreguntasDelBanco($idActividad, $idCalificacion, (int) $n, $kNuevo);
            self::reemplazarMarcadoresIntento($idCalificacion, $ids);
            self::persistirMetaAsignacion($idCalificacion, (int) $n, $kNuevo, true, null, null, null, true);

            return;
        }

        if (!$finalizado && $idsActuales === null) {
            $ids = self::seleccionarNPreguntasDelBanco($idActividad, $idCalificacion, (int) $n, $k);
            self::guardarMarcadores($idCalificacion, $ids);
            self::persistirMetaAsignacion($idCalificacion, (int) $n, $k, true);
        }
    }

    /**
     * Escribe/actualiza n, k, open en calificacionEstandart preservando ok/c/t si existen.
     * Con $limpiarResultadoIntento=true (nuevo intento) no se conservan c/t del intento anterior.
     */
    public static function persistirMetaAsignacion(
        int $idCalificacion,
        int $cantidad,
        int $intento,
        bool $open,
        ?bool $cumpleMinimo = null,
        ?int $correctas = null,
        ?int $total = null,
        bool $limpiarResultadoIntento = false,
        ?int $tiempoMinutos = null
    ): void {
        if (!Schema::hasTable('calificacionActividad') || !Schema::hasColumn('calificacionActividad', 'calificacionEstandart')) {
            return;
        }
        $prev = DB::table('calificacionActividad')->where('id', $idCalificacion)->value('calificacionEstandart');
        $metaPrev = self::leerMetaIntentoCuestionario($prev !== null ? (string) $prev : null);
        $ok = $cumpleMinimo !== null ? $cumpleMinimo : $metaPrev['cumpleMinimo'];
        if ($limpiarResultadoIntento) {
            $c = null;
            $t = null;
        } else {
            $c = $correctas !== null ? $correctas : $metaPrev['correctas'];
            $t = $total !== null ? $total : $metaPrev['total'];
            if ($correctas !== null || $total !== null) {
                // Al calificar un intento nuevo: actualizar c/t; open=false
                $open = false;
            }
        }

        $inicioUnix = $metaPrev['inicioUnix'] ?? null;
        $timeout = !empty($metaPrev['timeout']);
        if ($limpiarResultadoIntento) {
            $inicioUnix = now()->timestamp;
            $timeout = false;
        } elseif ($open && ($inicioUnix === null || $inicioUnix <= 0)) {
            $inicioUnix = now()->timestamp;
            $timeout = false;
        }

        $raw = self::escribirMetaIntentoCuestionario($ok, $c, $t, $cantidad, $intento, $open, $inicioUnix, $timeout, $tiempoMinutos ?? $metaPrev['tiempoMinutos']);
        DB::table('calificacionActividad')->where('id', $idCalificacion)->update([
            'calificacionEstandart' => $raw,
            'updated_at' => now(),
        ]);
    }

    public static function claveIntentoDesdeMeta(?array $meta): int
    {
        if (!$meta) {
            return 0;
        }

        return (int) ($meta['intento'] ?? 0);
    }

    /**
     * Clave de intento (k) desde meta persistida en calificacionEstandart.
     */
    public static function claveIntentoParaCalificacion(int $idCalificacion): int
    {
        if (!Schema::hasTable('calificacionActividad') || !Schema::hasColumn('calificacionActividad', 'calificacionEstandart')) {
            return 0;
        }
        $raw = DB::table('calificacionActividad')->where('id', $idCalificacion)->value('calificacionEstandart');

        return self::claveIntentoDesdeMeta(
            self::leerMetaIntentoCuestionario($raw !== null ? (string) $raw : null)
        );
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
     * Orden estable por idCalificacion + intento (k), distinto entre aprendices/reintentos.
     * Semilla derivada de IDs persistidos (sin random() por request).
     *
     * @param array<int, array<string, mixed>> $preguntas
     * @return array<int, array<string, mixed>>
     */
    public static function ordenarPreguntasYOpciones(array $preguntas, int $idCalificacion, int $idActividad): array
    {
        $k = self::claveIntentoParaCalificacion($idCalificacion);
        $preguntas = self::ordenarPorSemilla(
            array_values($preguntas),
            "orderQ:v2:{$idCalificacion}:{$idActividad}:k{$k}",
            fn ($p) => (int) (is_object($p) ? ($p->id ?? 0) : ($p['id'] ?? 0))
        );

        foreach ($preguntas as &$p) {
            $idPregunta = (int) (is_object($p) ? ($p->id ?? 0) : ($p['id'] ?? 0));
            $seedOpciones = "orderO:v2:{$idCalificacion}:{$idPregunta}:k{$k}";

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
        $k = self::claveIntentoParaCalificacion($idCalificacion);
        $items = $preguntas instanceof \Illuminate\Support\Collection
            ? $preguntas->values()->all()
            : array_values($preguntas);

        $items = self::ordenarPorSemilla(
            $items,
            "orderQ:v2:{$idCalificacion}:{$idActividad}:k{$k}",
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
                "orderO:v2:{$idCalificacion}:" . (int) $p->id . ":k{$k}",
                fn ($r) => (int) (is_object($r) ? $r->id : ($r['id'] ?? 0))
            );
            $p->setRelation('respuestas', collect(array_values($ops)));
        }

        return collect(array_values($items));
    }

    /**
     * ¿La asignación del cuestionario sigue vigente (antes o igual a fechaFinal)?
     * No confundir con "intento finalizado": el aprendiz puede haber terminado y la actividad seguir activa.
     */
    public static function asignacionCuestionarioActiva(?string $fechaFinalAsignacion, ?string $timezone = null): bool
    {
        if ($fechaFinalAsignacion === null || trim((string) $fechaFinalAsignacion) === '') {
            // Sin fecha límite conocida: tratar como activa (revisión restringida).
            return true;
        }
        $tz = $timezone ?: config('app.timezone');
        $ahora = now($tz);
        $limite = \Carbon\Carbon::parse((string) $fechaFinalAsignacion, $tz);

        return $ahora->lessThanOrEqualTo($limite);
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    private static function ordenarPorSemilla(array $items, string $seed, callable $getId): array
    {
        $items = array_values($items);
        $n = count($items);
        if ($n <= 1) {
            return $items;
        }

        // Orden canónico por ID: misma entrada + misma semilla ⇒ mismo resultado (estable en el intento).
        usort($items, function ($a, $b) use ($getId) {
            $idA = $getId($a);
            $idB = $getId($b);
            if ($idA === $idB) {
                return 0;
            }

            return $idA <=> $idB;
        });

        // Fisher–Yates determinista: la permutación depende de la semilla (aprendiz/intento), no del render.
        for ($i = $n - 1; $i > 0; $i--) {
            $hex = substr(hash('sha256', $seed . '|fy|' . $i), 0, 8);
            $j = hexdec($hex) % ($i + 1);
            if ($j === $i) {
                continue;
            }
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }

        return array_values($items);
    }

    /**
     * Meta de evaluación de cuestionario en calificacionActividad.calificacionEstandart.
     * Formato: CQ|ok={0|1}|c={correctas}|t={totalAsignadas}|n={cantidadPorIntento}|k={intento}|open={0|1}|ini={unix}|tout={0|1}
     * - ok: cumplió preguntasMinimasAprobar (solo estado académico APROBADO).
     * - c/t: correctas vs total del subconjunto del intento (100% => bloquea reintentos).
     * - n: cantidad de preguntas por intento configurada por el instructor.
     * - k: clave de intento (0 = primero; se incrementa en cada reintento regenerado).
     * - open: intento regenerado en curso (evita regenerar otra vez en F5).
     * - ini: unix del inicio real del intento (cuenta regresiva).
     * - tout: 1 si el intento se cerró por tiempo agotado.
     * - lim: minutos de esta asignación (0 = sin límite); se conserva en cada intento.
     *
     * @return array{
     *   cumpleMinimo: bool|null,
     *   correctas: int|null,
     *   total: int|null,
     *   puntajePerfecto: bool,
     *   cantidad: int|null,
     *   intento: int|null,
     *   open: bool,
     *   inicioUnix: int|null,
     *   timeout: bool,
     *   tiempoMinutos: int|null
     * }
     */
    public static function leerMetaIntentoCuestionario(?string $calificacionEstandart): array
    {
        $raw = trim((string) $calificacionEstandart);
        $vacio = [
            'cumpleMinimo' => null,
            'correctas' => null,
            'total' => null,
            'puntajePerfecto' => false,
            'cantidad' => null,
            'intento' => null,
            'open' => false,
            'inicioUnix' => null,
            'timeout' => false,
            'tiempoMinutos' => null,
        ];
        if ($raw === '' || !str_starts_with($raw, 'CQ|')) {
            return $vacio;
        }

        $cumple = null;
        if (preg_match('/\|ok=([01])/', $raw, $m)) {
            $cumple = $m[1] === '1';
        } elseif (preg_match('/\|ok=/', $raw)) {
            $cumple = null;
        }

        $correctas = null;
        $total = null;
        if (preg_match('/\|c=(\d+)/', $raw, $mc)) {
            $correctas = (int) $mc[1];
        }
        if (preg_match('/\|t=(\d+)/', $raw, $mt)) {
            $total = (int) $mt[1];
        }

        $cantidad = null;
        if (preg_match('/\|n=(\d+)/', $raw, $mn)) {
            $cantidad = (int) $mn[1];
        }

        $intento = null;
        if (preg_match('/\|k=(\d+)/', $raw, $mk)) {
            $intento = (int) $mk[1];
        }

        $open = false;
        if (preg_match('/\|open=([01])/', $raw, $mo)) {
            $open = $mo[1] === '1';
        }

        $inicioUnix = null;
        if (preg_match('/\|ini=(\d+)/', $raw, $mi)) {
            $inicioUnix = (int) $mi[1];
        }

        $timeout = false;
        if (preg_match('/\|tout=([01])/', $raw, $mto)) {
            $timeout = $mto[1] === '1';
        }

        $puntajePerfecto = $correctas !== null
            && $total !== null
            && $total > 0
            && $correctas === $total;

        return [
            'cumpleMinimo' => $cumple,
            'correctas' => $correctas,
            'total' => $total,
            'puntajePerfecto' => $puntajePerfecto,
            'cantidad' => $cantidad,
            'intento' => $intento,
            'open' => $open,
            'inicioUnix' => $inicioUnix,
            'timeout' => $timeout,
            'tiempoMinutos' => preg_match('/\|lim=(\d+)(?:\||$)/', $raw, $ml) ? (int) $ml[1] : null,
        ];
    }

    /**
     * @param  int|null  $correctas  Correctas del último intento (subconjunto asignado)
     * @param  int|null  $total  Total de preguntas del intento (no del banco)
     * @param  int|null  $cantidad  Preguntas por intento configuradas
     * @param  int|null  $intento  Clave de intento (k)
     * @param  bool|null $open  Intento regenerado en curso
     * @param  int|null  $inicioUnix  Inicio real del intento (unix)
     * @param  bool|null $timeout  Cierre por tiempo agotado
     */
    public static function escribirMetaIntentoCuestionario(
        ?bool $cumpleMinimo,
        ?int $correctas = null,
        ?int $total = null,
        ?int $cantidad = null,
        ?int $intento = null,
        ?bool $open = null,
        ?int $inicioUnix = null,
        ?bool $timeout = null,
        ?int $tiempoMinutos = null
    ): string {
        $parts = ['CQ'];
        if ($cumpleMinimo === null) {
            $parts[] = 'ok=';
        } else {
            $parts[] = 'ok=' . ($cumpleMinimo ? '1' : '0');
        }
        if ($correctas !== null && $total !== null && $total >= 0) {
            $parts[] = 'c=' . max(0, $correctas);
            $parts[] = 't=' . max(0, $total);
        }
        if ($cantidad !== null && $cantidad > 0) {
            $parts[] = 'n=' . $cantidad;
        }
        if ($intento !== null && $intento >= 0) {
            $parts[] = 'k=' . $intento;
        }
        if ($open !== null) {
            $parts[] = 'open=' . ($open ? '1' : '0');
        }
        if ($inicioUnix !== null && $inicioUnix > 0) {
            $parts[] = 'ini=' . $inicioUnix;
        }
        if ($timeout !== null) {
            $parts[] = 'tout=' . ($timeout ? '1' : '0');
        }

        if ($tiempoMinutos !== null) {
            $parts[] = 'lim=' . max(0, $tiempoMinutos);
        }

        return implode('|', $parts);
    }

    /**
     * True si el último intento acertó el 100% de las preguntas realmente asignadas.
     * Compara contra el subconjunto de la asignación, nunca contra el banco completo.
     */
    public static function tienePuntajePerfectoAsignacion(int $idCalificacion, int $idActividad): bool
    {
        $meta = null;
        if (Schema::hasTable('calificacionActividad') && Schema::hasColumn('calificacionActividad', 'calificacionEstandart')) {
            $raw = DB::table('calificacionActividad')->where('id', $idCalificacion)->value('calificacionEstandart');
            $meta = self::leerMetaIntentoCuestionario($raw !== null ? (string) $raw : null);
            if (!empty($meta['puntajePerfecto'])) {
                return true;
            }
            // Meta con c/t explícitos e incompleto ⇒ no perfecto
            if ($meta['correctas'] !== null && $meta['total'] !== null && $meta['total'] > 0) {
                return false;
            }
        }

        // Fallback: recalcular sobre el subconjunto asignado (misma regla que calificación automática).
        $tbl = self::tablaRespuestaCuestionarios();
        if (!$tbl || !Schema::hasTable('preguntas') || !Schema::hasTable('tipoPreguntas')) {
            return false;
        }

        $preguntasVariasOpciones = DB::table('preguntas as p')
            ->join('tipoPreguntas as tp', 'p.idTipoPregunta', '=', 'tp.id')
            ->where('p.idActividad', $idActividad)
            ->whereRaw("LOWER(TRIM(tp.tipoPregunta)) = 'varias opciones'")
            ->pluck('p.id');

        $idsAsignados = self::idsParaCalificacion($idCalificacion);
        if ($idsAsignados !== null) {
            $setAsignados = array_flip($idsAsignados);
            $preguntasVariasOpciones = $preguntasVariasOpciones
                ->filter(fn ($id) => isset($setAsignados[(int) $id]))
                ->values();
        }

        $total = $preguntasVariasOpciones->count();
        if ($total <= 0) {
            return false;
        }

        $respuestasAlumno = DB::table($tbl)
            ->where('idCalificacion', $idCalificacion)
            ->whereIn('idPregunta', $preguntasVariasOpciones->all())
            ->get()
            ->filter(fn ($row) => !self::esMarcador($row))
            ->keyBy('idPregunta');

        $correctas = 0;
        foreach ($preguntasVariasOpciones as $idPregunta) {
            $resp = $respuestasAlumno->get($idPregunta);
            if (!$resp || empty($resp->idRespuesta)) {
                continue;
            }
            $esCorrecta = (bool) DB::table('respuestas')->where('id', $resp->idRespuesta)->value('chkCorrecta');
            if ($esCorrecta) {
                $correctas++;
            }
        }

        return $correctas === $total;
    }

    /**
     * Próximo momento en que el aprendiz puede reintentar, a partir de fechaCalificacion
     * (marcado al finalizar/calificar el último envío) + intervalo en minutos.
     */
    public static function proximaFechaReintento(
        ?string $fechaCalificacion,
        ?int $intervaloReintento,
        ?string $timezone = null
    ): ?\Carbon\Carbon {
        if ($intervaloReintento === null || $intervaloReintento <= 0) {
            return null;
        }
        if ($fechaCalificacion === null || trim($fechaCalificacion) === '') {
            return null;
        }

        $tz = $timezone ?: config('app.timezone');

        return \Carbon\Carbon::parse((string) $fechaCalificacion, $tz)
            ->addMinutes($intervaloReintento);
    }

    /**
     * ¿Puede iniciar/enviar otro intento según vigencia, 100% y intervalo?
     * Orden: 1) fecha límite 2) puntaje perfecto (100% del subconjunto) 3) intervalo.
     * Cumplir preguntasMinimasAprobar (aprobado) NO bloquea reintentos.
     *
     * @return array{permitido: bool, proximoIntentoEn: string|null, motivo: string|null}
     */
    public static function evaluarDisponibilidadReintento(
        ?string $fechaCalificacion,
        ?int $intervaloReintento,
        ?string $fechaFinalAsignacion,
        ?string $timezone = null,
        bool $puntajePerfecto = false
    ): array {
        $tz = $timezone ?: config('app.timezone');
        $ahora = now($tz);

        if ($fechaFinalAsignacion) {
            $limite = \Carbon\Carbon::parse((string) $fechaFinalAsignacion, $tz);
            if ($ahora->greaterThan($limite)) {
                return [
                    'permitido' => false,
                    'proximoIntentoEn' => null,
                    'motivo' => 'La actividad ya superó su fecha y hora límite.',
                ];
            }
        }

        // 100% de las preguntas asignadas: sin más reintentos (aunque quede tiempo / intervalo).
        if ($puntajePerfecto) {
            return [
                'permitido' => false,
                'proximoIntentoEn' => null,
                'motivo' => 'Ya obtuviste el 100% de las preguntas asignadas. No es necesario realizar otro intento.',
            ];
        }

        // Sin intervalo configurado: no aplica reintento por tiempo (legacy).
        if ($intervaloReintento === null || $intervaloReintento <= 0) {
            return [
                'permitido' => false,
                'proximoIntentoEn' => null,
                'motivo' => null,
            ];
        }

        // Aún no ha finalizado un intento: el flujo normal de primer envío aplica.
        if ($fechaCalificacion === null || trim((string) $fechaCalificacion) === '') {
            return [
                'permitido' => true,
                'proximoIntentoEn' => null,
                'motivo' => null,
            ];
        }

        $proximo = self::proximaFechaReintento($fechaCalificacion, $intervaloReintento, $tz);
        if ($proximo === null) {
            return [
                'permitido' => true,
                'proximoIntentoEn' => null,
                'motivo' => null,
            ];
        }

        // Si el próximo intento cae después del límite, no se permite (fecha límite manda).
        if ($fechaFinalAsignacion) {
            $limite = \Carbon\Carbon::parse((string) $fechaFinalAsignacion, $tz);
            if ($proximo->greaterThan($limite) && $ahora->lessThan($proximo)) {
                return [
                    'permitido' => false,
                    'proximoIntentoEn' => $proximo->toDateTimeString(),
                    'motivo' => 'El próximo reintento sería después de la fecha límite de la actividad.',
                ];
            }
        }

        if ($ahora->lessThan($proximo)) {
            return [
                'permitido' => false,
                'proximoIntentoEn' => $proximo->toDateTimeString(),
                'motivo' => 'Aún no ha transcurrido el intervalo mínimo entre intentos.',
            ];
        }

        return [
            'permitido' => true,
            'proximoIntentoEn' => $proximo->toDateTimeString(),
            'motivo' => null,
        ];
    }

    /**
     * ¿Hay respuestas reales del aprendiz (no solo marcadores de subconjunto)?
     */
    public static function tieneRespuestasReales(int $idCalificacion): bool
    {
        $tbl = self::tablaRespuestaCuestionarios();
        if (!$tbl) {
            return false;
        }

        $rows = DB::table($tbl)->where('idCalificacion', $idCalificacion)->get();

        foreach ($rows as $row) {
            if (self::esMarcador($row)) {
                continue;
            }
            if (!empty($row->idRespuesta) || trim((string) ($row->respuesta ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** El límite pertenece a calificacionActividad; lim=0 indica una asignación sin límite. */
    public static function minutosTiempoCuestionario(?object $asignacion): ?int
    {
        $meta = self::leerMetaIntentoCuestionario($asignacion->calificacionEstandart ?? null);
        $minutos = $meta['tiempoMinutos'];

        return $minutos !== null && $minutos > 0 ? $minutos : null;
    }

    public static function guardarConfiguracionGrupo(int $idAsignacionGrupo, int $cantidad, ?int $minutos): void
    {
        $guardado = Storage::disk('local')->put("cuestionarios/asignaciones-grupo/{$idAsignacionGrupo}.json", json_encode([
            'cantidadPreguntas' => $cantidad,
            'tiempoCuestionario' => $minutos ?? 0,
        ], JSON_THROW_ON_ERROR));
        if (!$guardado) {
            throw new \RuntimeException('No se pudo guardar la configuración del cuestionario para el grupo.');
        }
    }

    public static function leerConfiguracionGrupo(int $idAsignacionGrupo): ?array
    {
        $path = "cuestionarios/asignaciones-grupo/{$idAsignacionGrupo}.json";
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }
        $config = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($config) || !isset($config['cantidadPreguntas'], $config['tiempoCuestionario'])
            || !is_int($config['cantidadPreguntas']) || $config['cantidadPreguntas'] < 1
            || !is_int($config['tiempoCuestionario']) || $config['tiempoCuestionario'] < 0) {
            throw new \RuntimeException('No se pudo leer la configuración del cuestionario para el grupo.');
        }

        return $config;
    }

    /**
     * Marca el inicio del intento la primera vez que el aprendiz entra a responder.
     */
    public static function marcarInicioIntento(int $idCalificacion): void
    {
        if (!Schema::hasTable('calificacionActividad') || !Schema::hasColumn('calificacionActividad', 'calificacionEstandart')) {
            return;
        }
        $ca = DB::table('calificacionActividad')->where('id', $idCalificacion)->first();
        if (!$ca) {
            return;
        }
        $meta = self::leerMetaIntentoCuestionario(
            isset($ca->calificacionEstandart) ? (string) $ca->calificacionEstandart : null
        );
        if (!empty($ca->fechaCalificacion) && empty($meta['open'])) {
            return;
        }
        if (!empty($meta['inicioUnix']) && (int) $meta['inicioUnix'] > 0) {
            return;
        }

        $raw = self::escribirMetaIntentoCuestionario(
            $meta['cumpleMinimo'],
            $meta['correctas'],
            $meta['total'],
            $meta['cantidad'],
            $meta['intento'],
            true,
            now()->timestamp,
            false,
            $meta['tiempoMinutos']
        );
        DB::table('calificacionActividad')->where('id', $idCalificacion)->update([
            'calificacionEstandart' => $raw,
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{
     *   fechaInicio: string|null,
     *   fechaVencimiento: string|null,
     *   segundosRestantes: int|null,
     *   vencido: bool,
     *   cierrePorTiempo: bool,
     *   servidorAhora: string,
     *   tiempoCuestionario: int|null,
     *   inicioUnix: int|null
     * }
     */
    public static function estadoTemporizador(int $idCalificacion, ?int $minutos): array
    {
        $tz = config('app.timezone');
        $ahora = now($tz);
        $vacio = [
            'fechaInicio' => null,
            'fechaVencimiento' => null,
            'segundosRestantes' => null,
            'vencido' => false,
            'cierrePorTiempo' => false,
            'servidorAhora' => $ahora->toDateTimeString(),
            'tiempoCuestionario' => $minutos,
            'inicioUnix' => null,
        ];
        if (!Schema::hasTable('calificacionActividad')) {
            return $vacio;
        }
        $ca = DB::table('calificacionActividad')->where('id', $idCalificacion)->first();
        if (!$ca) {
            return $vacio;
        }
        $meta = self::leerMetaIntentoCuestionario(
            isset($ca->calificacionEstandart) ? (string) $ca->calificacionEstandart : null
        );
        $inicioUnix = !empty($meta['inicioUnix']) ? (int) $meta['inicioUnix'] : null;
        $vencimiento = ($inicioUnix && $minutos !== null && $minutos > 0)
            ? \Carbon\Carbon::createFromTimestamp($inicioUnix, $tz)->addMinutes($minutos)
            : null;
        $segundosRestantes = $vencimiento !== null
            ? max(0, $vencimiento->getTimestamp() - $ahora->getTimestamp())
            : null;

        return [
            'fechaInicio' => $inicioUnix
                ? \Carbon\Carbon::createFromTimestamp($inicioUnix, $tz)->toDateTimeString()
                : null,
            'fechaVencimiento' => $vencimiento?->toDateTimeString(),
            'segundosRestantes' => $segundosRestantes,
            'vencido' => $vencimiento !== null && $ahora->greaterThanOrEqualTo($vencimiento),
            'cierrePorTiempo' => !empty($meta['timeout']),
            'servidorAhora' => $ahora->toDateTimeString(),
            'tiempoCuestionario' => $minutos,
            'inicioUnix' => $inicioUnix,
        ];
    }

    /**
     * @return array{
     *   fechaInicioIntento: string|null,
     *   fechaFinIntento: string|null,
     *   tiempoUtilizadoSegundos: int|null,
     *   cierrePorTiempo: bool,
     *   estadoCierre: string|null
     * }
     */
    public static function resumenTiempoIntento(?string $calificacionEstandart, ?string $fechaCalificacion): array
    {
        $meta = self::leerMetaIntentoCuestionario($calificacionEstandart);
        $inicioUnix = !empty($meta['inicioUnix']) ? (int) $meta['inicioUnix'] : null;
        $tz = config('app.timezone');
        $inicio = $inicioUnix
            ? \Carbon\Carbon::createFromTimestamp($inicioUnix, $tz)
            : null;
        $fin = ($fechaCalificacion !== null && trim($fechaCalificacion) !== '')
            ? \Carbon\Carbon::parse($fechaCalificacion, $tz)
            : null;
        $segundos = ($inicio && $fin)
            ? max(0, $fin->getTimestamp() - $inicio->getTimestamp())
            : null;
        $timeout = !empty($meta['timeout']);
        $estadoCierre = null;
        if ($inicio && !$fin) {
            $estadoCierre = 'EN CURSO';
        } elseif ($fin) {
            $estadoCierre = $timeout ? 'TIEMPO AGOTADO' : 'FINALIZADO';
        }

        return [
            'fechaInicioIntento' => $inicio?->toDateTimeString(),
            'fechaFinIntento' => $fin?->toDateTimeString(),
            'tiempoUtilizadoSegundos' => $segundos,
            'cierrePorTiempo' => $timeout,
            'estadoCierre' => $estadoCierre,
        ];
    }
}
