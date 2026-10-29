<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia idempotente de urlDocumento / urlVideo / urlAdicional a materialCompetenciaRecurso.
 * No duplica por (material, tipo, ruta). No omite un documento si ya existe un video.
 */
class MaterialCompetenciaRecursoSync
{
    /**
     * @return array{insertados: int, omitidos: int}
     */
    public static function consolidarDesdeCamposLegacy(): array
    {
        $insertados = 0;
        $omitidos = 0;
        if (! Schema::hasTable('materialCompetencia') || ! Schema::hasTable('materialCompetenciaRecurso')) {
            return compact('insertados', 'omitidos');
        }

        $tieneVideo = Schema::hasColumn('materialCompetencia', 'urlVideo');
        $existentes = [];
        foreach (DB::table('materialCompetenciaRecurso')->get(['idMaterialCompetencia', 'tipo', 'ruta']) as $row) {
            $existentes[self::clave((int) $row->idMaterialCompetencia, (string) $row->tipo, (string) $row->ruta)] = true;
        }

        $now = now();
        foreach (DB::table('materialCompetencia')->orderBy('id')->get() as $m) {
            $id = (int) $m->id;
            $orden = (int) (DB::table('materialCompetenciaRecurso')->where('idMaterialCompetencia', $id)->max('orden') ?? -1);

            $candidatos = [];
            $doc = trim((string) ($m->urlDocumento ?? ''));
            if ($doc !== '') {
                $candidatos[] = ['DOCUMENTO', $doc, MaterialCompetenciaEnlace::formatoDesdeRuta($doc) ?: 'documento'];
            }
            if ($tieneVideo) {
                $video = trim((string) ($m->urlVideo ?? ''));
                if ($video !== '') {
                    $clasif = MaterialCompetenciaEnlace::clasificar($video);
                    if (($clasif['formato'] ?? null) === MaterialCompetenciaEnlace::FORMATO_YOUTUBE) {
                        $candidatos[] = ['ENLACE', $clasif['url'], 'youtube'];
                    } else {
                        $candidatos[] = ['MULTIMEDIA', $video, MaterialCompetenciaEnlace::formatoDesdeRuta($video) ?: 'video'];
                    }
                }
            }
            $enlace = trim((string) ($m->urlAdicional ?? ''));
            if ($enlace !== '') {
                $clasif = MaterialCompetenciaEnlace::clasificar($enlace);
                $ruta = $clasif['ok'] ?? false ? $clasif['url'] : $enlace;
                $formato = $clasif['ok'] ?? false ? $clasif['formato'] : 'enlace';
                $candidatos[] = ['ENLACE', $ruta, $formato];
            }

            foreach ($candidatos as [$tipo, $ruta, $formato]) {
                $clave = self::clave($id, $tipo, $ruta);
                if (isset($existentes[$clave])) {
                    $omitidos++;
                    continue;
                }
                $orden++;
                DB::table('materialCompetenciaRecurso')->insert([
                    'idMaterialCompetencia' => $id,
                    'tipo' => $tipo,
                    'titulo' => null,
                    'descripcion' => null,
                    'ruta' => mb_substr($ruta, 0, 1000),
                    'formato' => $formato ? mb_substr((string) $formato, 0, 32) : null,
                    'orden' => $orden,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $existentes[$clave] = true;
                $insertados++;
            }
        }

        return compact('insertados', 'omitidos');
    }

    public static function clave(int $idMaterial, string $tipo, string $ruta): string
    {
        return $idMaterial.'|'.$tipo.'|'.mb_strtolower(trim($ruta));
    }
}
