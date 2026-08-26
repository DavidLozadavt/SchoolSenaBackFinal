<?php

namespace App\Util;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        self::persistirMetaAsignacion($idCalificacionNueva, (int) $n, 0, false);
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
        bool $limpiarResultadoIntento = false
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

        $raw = self::escribirMetaIntentoCuestionario($ok, $c, $t, $cantidad, $intento, $open);
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
     * Formato: CQ|ok={0|1}|c={correctas}|t={totalAsignadas}|n={cantidadPorIntento}|k={intento}|open={0|1}
     * - ok: cumplió preguntasMinimasAprobar (solo estado académico APROBADO).
     * - c/t: correctas vs total del subconjunto del intento (100% => bloquea reintentos).
     * - n: cantidad de preguntas por intento configurada por el instructor.
     * - k: clave de intento (0 = primero; se incrementa en cada reintento regenerado).
     * - open: intento regenerado en curso (evita regenerar otra vez en F5).
     *
     * @return array{
     *   cumpleMinimo: bool|null,
     *   correctas: int|null,
     *   total: int|null,
     *   puntajePerfecto: bool,
     *   cantidad: int|null,
     *   intento: int|null,
     *   open: bool
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
        ];
    }

    /**
     * @param  int|null  $correctas  Correctas del último intento (subconjunto asignado)
     * @param  int|null  $total  Total de preguntas del intento (no del banco)
     * @param  int|null  $cantidad  Preguntas por intento configuradas
     * @param  int|null  $intento  Clave de intento (k)
     * @param  bool|null $open  Intento regenerado en curso
     */
    public static function escribirMetaIntentoCuestionario(
        ?bool $cumpleMinimo,
        ?int $correctas = null,
        ?int $total = null,
        ?int $cantidad = null,
        ?int $intento = null,
        ?bool $open = null
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
}
