<?php

namespace App\Services;

use App\Models\GradoMateria;
use App\Models\GradoPrograma;
use App\Models\HorarioMateria;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para proteger trimestres históricos de una ficha.
 */
class TrimestreActualFichaService
{
    public static function maxNumeroGradoFicha(int $idFicha): int
    {
        $max = DB::table('horarioMateria as hm')
            ->join('gradoMateria as gm', 'gm.id', '=', 'hm.idGradoMateria')
            ->join('gradoPrograma as gp', 'gp.id', '=', 'gm.idGradoPrograma')
            ->join('grado as g', 'g.id', '=', 'gp.idGrado')
            ->where('hm.idFicha', $idFicha)
            ->max(DB::raw('CAST(g.numeroGrado AS UNSIGNED)'));

        return (int) ($max ?? 0);
    }

    public static function numeroGradoDeGradoPrograma(int $idGradoPrograma): ?int
    {
        $gp = GradoPrograma::with('grado')->find($idGradoPrograma);
        if (!$gp || !$gp->grado) {
            return null;
        }
        $n = (int) $gp->grado->numeroGrado;

        return $n > 0 ? $n : null;
    }

    public static function esGradoProgramaActual(int $idGradoPrograma, int $idFicha): bool
    {
        $max = self::maxNumeroGradoFicha($idFicha);
        if ($max <= 0) {
            return false;
        }
        $n = self::numeroGradoDeGradoPrograma($idGradoPrograma);

        return $n !== null && $n === $max;
    }

    public static function esGradoMateriaActual(int $idGradoMateria, int $idFicha): bool
    {
        $gm = GradoMateria::find($idGradoMateria);
        if (!$gm) {
            return false;
        }

        return self::esGradoProgramaActual((int) $gm->idGradoPrograma, $idFicha);
    }

    public static function esHorarioActual(int $idHorarioMateria): bool
    {
        $h = HorarioMateria::find($idHorarioMateria);
        if (!$h || !$h->idFicha || !$h->idGradoMateria) {
            return false;
        }

        return self::esGradoMateriaActual((int) $h->idGradoMateria, (int) $h->idFicha);
    }

    public static function respuestaBloqueo(): JsonResponse
    {
        return response()->json([
            'message' => 'Solo puede modificarse el trimestre actual. Los trimestres históricos están protegidos.',
        ], 403);
    }

    public static function abortSiGradoProgramaNoActual(int $idGradoPrograma, int $idFicha): ?JsonResponse
    {
        if (!self::esGradoProgramaActual($idGradoPrograma, $idFicha)) {
            return self::respuestaBloqueo();
        }

        return null;
    }

    public static function abortSiGradoMateriaNoActual(int $idGradoMateria, ?int $idFicha = null): ?JsonResponse
    {
        if ($idFicha) {
            if (!self::esGradoMateriaActual($idGradoMateria, $idFicha)) {
                return self::respuestaBloqueo();
            }

            return null;
        }

        $fichas = HorarioMateria::where('idGradoMateria', $idGradoMateria)
            ->pluck('idFicha')
            ->unique()
            ->filter();

        if ($fichas->isEmpty()) {
            return self::respuestaBloqueo();
        }

        foreach ($fichas as $fid) {
            if (!self::esGradoMateriaActual($idGradoMateria, (int) $fid)) {
                return self::respuestaBloqueo();
            }
        }

        return null;
    }

    public static function abortSiHorarioNoActual(int $idHorarioMateria): ?JsonResponse
    {
        if (!self::esHorarioActual($idHorarioMateria)) {
            return self::respuestaBloqueo();
        }

        return null;
    }

    /**
     * @param  array<int|array{id?:int}>  $horarios
     */
    public static function abortSiAlgunHorarioNoActual(array $horarios): ?JsonResponse
    {
        foreach ($horarios as $h) {
            $id = is_array($h) ? ($h['id'] ?? null) : $h;
            if (!$id) {
                continue;
            }
            $block = self::abortSiHorarioNoActual((int) $id);
            if ($block) {
                return $block;
            }
        }

        return null;
    }
}
