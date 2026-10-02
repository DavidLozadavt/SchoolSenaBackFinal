<?php

namespace App\Http\Controllers;

use App\Models\GrupoMultimedia;
use App\Models\MultimediaHistorias;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;


class MultimediaHistoriasController extends Controller
{
    // ─── GET: grupos (historias o reels) por empresa ────────────────────────────
    public function getGruposMultimedia(Request $request)
    {
        $idCompany = KeyUtil::idCompany();
        $tipo = $request->query('tipo'); // 'historia' | 'reel' | null
        $archived = filter_var($request->input('archived', false), FILTER_VALIDATE_BOOLEAN);

        $query = GrupoMultimedia::where('idCompany', $idCompany)
            ->with(['gruposMultimedia', 'user']);

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        if ($tipo === 'historia') {
            if ($archived) {
                // Historias de más de 24 horas
                $query->where('created_at', '<', now()->subHours(24));
            } else {
                // Historias de menos de 24 horas
                $query->where('created_at', '>=', now()->subHours(24));
            }
        } elseif ($tipo === 'reel') {
            if ($archived) {
                // Reels de más de 72 horas
                $query->where('created_at', '<', now()->subHours(72));
            } else {
                // Reels de menos de 72 horas
                $query->where('created_at', '>=', now()->subHours(72));
            }
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }


    // ─── POST: crear grupo + sus archivos ────────────────────────────────────────
    public function storeMultimediaGrupo(Request $request)
    {
        DB::beginTransaction();
        try {
            $tipo            = $request->input('tipo', 'historia');
            $archivos        = $request->file('archivos', []);
            $archivosCancion = $request->input('archivos_cancion', []);
            $urlDirecta      = $request->input('url');

            $grupoMultimedia = new GrupoMultimedia();
            $grupoMultimedia->idCompany   = KeyUtil::idCompany();
            $grupoMultimedia->nombreGrupo = $request->input('nombreGrupo');
            $grupoMultimedia->tipo        = $tipo;
            $grupoMultimedia->descripcion = $request->input('descripcion');
            $grupoMultimedia->idUser      = KeyUtil::user()->id;
            $grupoMultimedia->save();

            $multimedias = [];

            if (!empty($archivos)) {
                foreach ($archivos as $index => $archivo) {
                    $multimediaPos = new MultimediaHistorias();
                    $multimediaPos->idGrupoMultimedia = $grupoMultimedia->id;
                    $multimediaPos->idCompany         = KeyUtil::idCompany();
                    $multimediaPos->idUser            = KeyUtil::user()->id;
                    $multimediaPos->tipo              = $tipo;
                    $multimediaPos->urlMultimedia     = '/storage/' . $archivo->store(
                        MultimediaHistorias::RUTA_MULTIMEDIA,
                        ['disk' => 'public']
                    );

                    if (isset($archivosCancion[$index])) {
                        $multimediaPos->cancion = json_encode($archivosCancion[$index]);
                    }

                    $multimediaPos->save();
                    $multimedias[] = $multimediaPos;
                }
            } 
            elseif ($urlDirecta) {
                $multimediaPos = new MultimediaHistorias();
                $multimediaPos->idGrupoMultimedia = $grupoMultimedia->id;
                $multimediaPos->idCompany         = KeyUtil::idCompany();
                $multimediaPos->idUser            = KeyUtil::user()->id;
                $multimediaPos->tipo              = $tipo;
                $multimediaPos->urlMultimedia     = $urlDirecta;

                if (!empty($archivosCancion)) {
                    $multimediaPos->cancion = json_encode($archivosCancion[0] ?? $archivosCancion);
                }

                $multimediaPos->save();
                $multimedias[] = $multimediaPos;
            }

            DB::commit();

            return response()->json([
                'grupo'      => $grupoMultimedia,
                'multimedias' => $multimedias,
                'message'    => 'Grupo y multimedia creados'
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar multimedia',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    // ─── POST: actualizar grupo + sus archivos ───────────────────────────────────
    public function updateMultimediaGrupo(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $grupoMultimedia = GrupoMultimedia::findOrFail($id);
            $grupoMultimedia->nombreGrupo = $request->input('nombreGrupo', $grupoMultimedia->nombreGrupo);
            $grupoMultimedia->descripcion = $request->input('descripcion', $grupoMultimedia->descripcion);
            if ($request->filled('tipo')) {
                $grupoMultimedia->tipo = $request->input('tipo');
            }
            $grupoMultimedia->save();

            // Manejar actualización de URL para Reels
            if ($grupoMultimedia->tipo === 'reel' && $request->filled('url')) {
                $url = $request->input('url');
                $item = MultimediaHistorias::where('idGrupoMultimedia', $grupoMultimedia->id)
                    ->where(function($q) {
                        $q->where('urlMultimedia', 'like', 'http%')
                          ->orWhere('urlMultimedia', 'like', 'www%');
                    })->first();

                if ($item) {
                    $item->urlMultimedia = $url;
                    $item->save();
                } else {
                    $item = new MultimediaHistorias();
                    $item->idGrupoMultimedia = $grupoMultimedia->id;
                    $item->idCompany = $grupoMultimedia->idCompany;
                    $item->idUser = $grupoMultimedia->idUser ?? KeyUtil::user()->id;
                    $item->tipo = 'reel';
                    $item->urlMultimedia = $url;
                    $item->save();
                }
            }

            $archivosIds             = $request->input('archivos_ids', []);
            $archivosFiles           = $request->file('archivos', []);
            $archivosCancionExist    = $request->input('archivos_cancion_existentes', []);
            $archivosCancionNuevos   = $request->input('archivos_cancion_nuevos', []);

            foreach ($archivosCancionNuevos as $i => $trackJson) {
                $archivosCancionNuevos[$i] = json_decode($trackJson, true);
            }

            foreach ($archivosIds as $existingId) {
                $multimediaPos = MultimediaHistorias::find($existingId);
                if (!$multimediaPos) continue;

                if (isset($archivosCancionExist[$existingId])) {
                    $multimediaPos->cancion = json_encode($archivosCancionExist[$existingId]);
                }

                if (isset($archivosFiles[$existingId])) {
                    $archivo = $archivosFiles[$existingId];
                    $multimediaPos->urlMultimedia = '/storage/' . $archivo->store(
                        MultimediaHistorias::RUTA_MULTIMEDIA, ['disk' => 'public']
                    );
                }

                $multimediaPos->save();
            }

            $nuevosArchivos = [];
            foreach ($archivosFiles as $key => $archivo) {
                if (!in_array($key, $archivosIds)) {
                    $nuevosArchivos[] = $archivo;
                }
            }

            foreach ($nuevosArchivos as $index => $archivo) {
                $multimediaPos = new MultimediaHistorias();
                $multimediaPos->idGrupoMultimedia = $grupoMultimedia->id;
                $multimediaPos->idCompany         = KeyUtil::idCompany();
                $multimediaPos->idUser            = KeyUtil::user()->id;
                $multimediaPos->tipo              = $grupoMultimedia->tipo;
                $multimediaPos->urlMultimedia     = '/storage/' . $archivo->store(
                    MultimediaHistorias::RUTA_MULTIMEDIA, ['disk' => 'public']
                );

                if (isset($archivosCancionNuevos[$index])) {
                    $multimediaPos->cancion = json_encode($archivosCancionNuevos[$index]);
                }

                $multimediaPos->save();
            }

            if ($request->filled('deleted_ids')) {
                $deletedIds = json_decode($request->input('deleted_ids'), true);
                MultimediaHistorias::whereIn('id', $deletedIds)->delete();
            }

            DB::commit();
            return response()->json($grupoMultimedia->load('gruposMultimedia'), 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar grupo multimedia',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    // ─── DELETE: eliminar grupo y sus historias/reels ───────────────────────────
    public function destroyGrupoMultimedia($id)
    {
        $grupoMultimedia = GrupoMultimedia::findOrFail($id);
        
        // Limpiar referencias en la tabla de eventos antes de borrar el grupo
        \App\Models\Evento::where('idGrupoMultimedia', $id)->update(['idGrupoMultimedia' => null]);

        $grupoMultimedia->gruposMultimedia()->delete();
        $grupoMultimedia->delete();

        return response()->json(['message' => 'Grupo eliminado correctamente'], 200);
    }


    // ─── DELETE: eliminar multimedia individual ─────────────────────────────────
    public function destroyMultimediaUser($id)
    {
        $multimedia = MultimediaHistorias::findOrFail($id);
        $multimedia->delete();
        return response()->json(['message' => 'Eliminado correctamente'], 200);
    }


    // ─── GET: historias activas (últimas 24h) agrupadas ─────────────────────────
    public function getStoriesGrouped()
    {
        $grupos = GrupoMultimedia::with([
            'empresa',
            'gruposMultimedia' => function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subHours(24));
            }
        ])
            ->where('tipo', 'historia')
            ->whereHas('gruposMultimedia', function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subHours(24));
            })
            ->get()
            ->groupBy('idCompany');

        return response()->json($grupos);
    }


    // ─── GET: todas las historias de la empresa ──────────────────────────────────
    public function getStories()
    {
        $idCompany = KeyUtil::idCompany();

        $grupos = MultimediaHistorias::where('idCompany', $idCompany)
            ->where('tipo', 'historia')
            ->where('created_at', '>=', now()->subHours(24))
            ->with('grupoMultimedia', 'user.persona')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($grupos);
    }


    // ─── GET: todos los reels de la empresa ──────────────────────────────────────
    public function getReels()
    {
        $idCompany = KeyUtil::idCompany();

        $grupos = GrupoMultimedia::where('idCompany', $idCompany)
            ->where('tipo', 'reel')
            ->with(['gruposMultimedia', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($grupos);
    }


    // ─── GET: grupos multimedia para dashboard (historias + reels) ──────────────
    public function getDashboardMultimedia()
    {
        $idCompany = KeyUtil::idCompany();

        $historias = GrupoMultimedia::where('idCompany', $idCompany)
            ->where('tipo', 'historia')
            ->whereHas('gruposMultimedia', function ($query) {
                $query->where('created_at', '>=', now()->subHours(24));
            })
            ->with(['gruposMultimedia' => function ($query) {
                $query->where('created_at', '>=', now()->subHours(24));
            }])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($g) => array_merge($g->toArray(), ['tipo_item' => 'historia']));

        $reels = GrupoMultimedia::where('idCompany', $idCompany)
            ->where('tipo', 'reel')
            ->whereHas('gruposMultimedia', function ($query) {
                $query->where('created_at', '>=', now()->subHours(72));
            })
            ->with(['gruposMultimedia' => function ($query) {
                $query->where('created_at', '>=', now()->subHours(72));
            }])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($g) => array_merge($g->toArray(), ['tipo_item' => 'reel']));

        return response()->json([
            'historias' => $historias,
            'reels'     => $reels,
        ]);
    }


    // ─── GET: mis historias/reels ────────────────────────────────────────────────
    public function getStoriesByUser()
    {
        $idCompany = KeyUtil::idCompany();
        $idUser    = KeyUtil::user()->id;

        $grupos = GrupoMultimedia::where('idCompany', $idCompany)
            ->where('idUser', $idUser)
            ->with(['gruposMultimedia', 'user'])
            ->get();

        return response()->json($grupos);
    }


    // ─── Deezer: buscar canción ──────────────────────────────────────────────────
    public function searchTrack(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['error' => 'Missing query'], 400);
        }

        $response = Http::get("https://api.deezer.com/search", [
            'q'     => $query,
            'limit' => 10
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error fetching Deezer API'], 500);
        }

        $tracks = collect($response->json()['data'])->map(function ($item) {
            return [
                'id'          => $item['id'],
                'title'       => $item['title'],
                'artist'      => $item['artist']['name'],
                'preview_url' => $item['preview'],
                'image'       => $item['album']['cover_medium'] ?? null,
            ];
        });

        return response()->json($tracks);
    }


    // ─── Deezer: obtener track por id ────────────────────────────────────────────
    public function getTrack($id)
    {
        $response = Http::get("https://api.deezer.com/track/$id");

        if ($response->failed()) {
            return response()->json(['error' => 'Error fetching track'], 500);
        }

        $track = $response->json();

        return response()->json([
            'id'          => $track['id'],
            'title'       => $track['title'],
            'artist'      => $track['artist']['name'],
            'preview_url' => $track['preview'],
            'image'       => $track['album']['cover_medium'] ?? null,
        ]);
    }
}
