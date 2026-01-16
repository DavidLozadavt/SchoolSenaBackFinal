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
    public function getGruposMultimedia()
    {
        $idCompany = KeyUtil::idCompany();
        $idUser = KeyUtil::user()->id;
        $grupos = GrupoMultimedia::where('idCompany', $idCompany)
            ->with('gruposMultimedia', 'user')
            ->get();

        return response()->json($grupos);
    }


    public function storeMultimediaGrupo(Request $request)
    {
        DB::beginTransaction();
        try {
            $descripcion = $request->input('descripcion', '');
            $background = $request->input('background', '');
            $archivos = $request->file('archivos', []);
            $archivosCancion = $request->input('archivos_cancion', []);

            $grupoMultimedia = null;
            if ($request->filled('nombreGrupo')) {
                $grupoMultimedia = new GrupoMultimedia();
                $grupoMultimedia->idCompany = KeyUtil::idCompany();
                $grupoMultimedia->nombreGrupo = $request->input('nombreGrupo');
                //$grupoMultimedia->idUser = KeyUtil::user()->id;
                $grupoMultimedia->save();
            }

            $multimedias = [];

            foreach ($archivos as $index => $archivo) {
                $multimediaPos = new MultimediaHistorias();
                $multimediaPos->idGrupoMultimedia = $grupoMultimedia?->id;
                //$multimediaPos->idUser = KeyUtil::user()->id;
                //$multimediaPos->idCompany = KeyUtil::idCompany();
                //$multimediaPos->descripcion = $descripcion;
                //$multimediaPos->background = $background;
                $multimediaPos->urlMultimedia = '/storage/' . $archivo->store(
                    MultimediaHistorias::RUTA_MULTIMEDIA,
                    ['disk' => 'public']
                );

                if (isset($archivosCancion[$index])) {
                    $multimediaPos->cancion = json_encode($archivosCancion[$index]);
                }

                $multimediaPos->save();
                $multimedias[] = $multimediaPos;
            }

            DB::commit();

            return response()->json([
                'grupo' => $grupoMultimedia,
                'multimedias' => $multimedias,
                'message' => $grupoMultimedia
                    ? 'Grupo y multimedia creados'
                    : 'Multimedia creada sin grupo'
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar multimedia',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function updateMultimediaGrupo(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $grupoMultimedia = GrupoMultimedia::findOrFail($id);
            $grupoMultimedia->nombreGrupo = $request->input('nombreGrupo');
            $grupoMultimedia->save();


            $archivosIds = $request->input('archivos_ids', []);
            $archivosFiles = $request->file('archivos', []);
            $archivosCancionExistentes = $request->input('archivos_cancion_existentes', []);
            $archivosCancionNuevos = $request->input('archivos_cancion_nuevos', []);

            foreach ($archivosCancionNuevos as $i => $trackJson) {
                $archivosCancionNuevos[$i] = json_decode($trackJson, true);
            }

            foreach ($archivosIds as $existingId) {
                $multimediaPos = MultimediaHistorias::find($existingId);
                if (!$multimediaPos) continue;

                if (isset($archivosCancionExistentes[$existingId])) {
                    $multimediaPos->cancion = json_encode($archivosCancionExistentes[$existingId]);
                }

                if (isset($archivosFiles[$existingId])) {
                    $archivo = $archivosFiles[$existingId];
                    $multimediaPos->urlMultimedia = '/storage/' . $archivo->store(MultimediaHistorias::RUTA_MULTIMEDIA, ['disk' => 'public']);
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
                $multimediaPos->urlMultimedia = '/storage/' . $archivo->store(MultimediaHistorias::RUTA_MULTIMEDIA, ['disk' => 'public']);

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
            return response()->json($grupoMultimedia, 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar grupo multimedia',
                'error' => $e->getMessage()
            ], 500);
        }
    }







    public function destroyGrupoMultimedia($id)
    {
        $grupoMultimedia = GrupoMultimedia::findOrFail($id);


        $grupoMultimedia->gruposMultimedia()->delete();

        $grupoMultimedia->delete();

        return response()->json(['message' => 'Grupo eliminado correctamente'], 200);
    }

    
    public function destroyMultimediaUser($id)
    {
        $multimedia = MultimediaHistorias::findOrFail($id);

        if ($multimedia->idUser !== KeyUtil::user()->id) {
            return response()->json(['message' => 'No tienes permisos para eliminar esta historia'], 403);
        }

        $multimedia->delete();

        return response()->json(['message' => 'Eliminado correctamente'], 200);
    }



    public function getStoriesGrouped()
    {
        $grupos = GrupoMultimedia::with([
            'empresa',
            'gruposMultimedia' => function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subHours(24));
            }
        ])
            ->whereHas('gruposMultimedia', function ($query) {
                $query->where('created_at', '>=', Carbon::now()->subHours(24));
            })
            ->get()
            ->groupBy('idCompany');

        return response()->json($grupos);
    }



    public function getStories()
    {
        $idCompany = KeyUtil::idCompany();

        $grupos = MultimediaHistorias::where('idCompany', $idCompany)
            ->where('created_at', '>=', now()->subHours(24))
            ->with('gruposMultimedia', 'user.persona')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($grupos);
    }


    public function getStoriesByUser()
    {
        $idCompany = KeyUtil::idCompany();
        $idUser = KeyUtil::user()->id;
        $grupos = MultimediaHistorias::where('idCompany', $idCompany)
        ->where('idUser', $idUser)

            ->with('gruposMultimedia', 'user.persona')
            ->get();

        return response()->json($grupos);
    }




    public function searchTrack(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['error' => 'Missing query'], 400);
        }

        $response = Http::get("https://api.deezer.com/search", [
            'q' => $query,
            'limit' => 10
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error fetching Deezer API'], 500);
        }

        $tracks = collect($response->json()['data'])->map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'artist' => $item['artist']['name'],
                'preview_url' => $item['preview'],
                'image' => $item['album']['cover_medium'] ?? null,
            ];
        });

        return response()->json($tracks);
    }



    public function getTrack($id)
    {
        $response = Http::get("https://api.deezer.com/track/$id");

        if ($response->failed()) {
            return response()->json(['error' => 'Error fetching track'], 500);
        }

        $track = $response->json();

        return response()->json([
            'id' => $track['id'],
            'title' => $track['title'],
            'artist' => $track['artist']['name'],
            'preview_url' => $track['preview'],
            'image' => $track['album']['cover_medium'] ?? null,
        ]);
    }
}
