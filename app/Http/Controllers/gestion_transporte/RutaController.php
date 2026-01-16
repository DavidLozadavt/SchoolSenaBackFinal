<?php

namespace App\Http\Controllers\gestion_transporte;

use App\Http\Controllers\Controller;
use App\Models\Ruta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\EstadoViaje;
use App\Util\KeyUtil;


class RutaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');

        $query = Ruta::with([
            'rutaVuelta:id,distancia,latitud,longitud,tiempoEstimado,descripcion,precio,idCiudadOrigen,idCiudadDestino,idRutaVuelta',
            'ciudadOrigen:id,codigo,descripcion,iddepartamento',
            'ciudadDestino:id,codigo,descripcion,iddepartamento',
            'rutaVuelta.ciudadOrigen:id,codigo,descripcion,iddepartamento',
            'rutaVuelta.ciudadDestino:id,codigo,descripcion,iddepartamento'
        ])->orderBy("id", "desc");

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('ciudadOrigen', function ($q) use ($search) {
                    $q->where('descripcion', 'like', "%{$search}%");
                })->orWhereHas('ciudadDestino', function ($q) use ($search) {
                    $q->where('descripcion', 'like', "%{$search}%");
                })->orWhereHas('rutaVuelta.ciudadOrigen', function ($q) use ($search) {
                    $q->where('descripcion', 'like', "%{$search}%");
                })->orWhereHas('rutaVuelta.ciudadDestino', function ($q) use ($search) {
                    $q->where('descripcion', 'like', "%{$search}%");
                });
            });
        }

        $rutas = $query->get();

        $agrupadas = collect();

        foreach ($rutas as $ruta) {
            if (!$agrupadas->contains(fn($group) => optional($group['rutaIda'])->id === $ruta->idRutaVuelta)) {
                $agrupadas->push([
                    'rutaIda'    => $ruta->rutaVuelta ? $ruta->rutaVuelta->makeHidden('rutaVuelta') : $ruta,
                    'rutaVuelta' => $ruta->makeHidden('rutaVuelta'),
                ]);
            }
        }

        return response()->json($agrupadas);
    }


    public function getRutasHijasByRutaPadre(Request $request, string $id): JsonResponse
    {
        $ruta = Ruta::with(['ciudadOrigen', 'ciudadDestino', 'rutasHijas.lugar'])
            ->where('id', $id)
            ->first();

        return response()->json($ruta);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $rutaIda = Ruta::create([
                'distancia'       => $request->distancia,
                'latitud'         => $request->latitud,
                'longitud'        => $request->longitud,
                'tiempoEstimado'  => $request->tiempoEstimado,
                'descripcion'     => $request->descripcion,
                'precio'          => $request->precio,
                'idCiudadOrigen'  => $request->idCiudadOrigen,
                'idCiudadDestino' => $request->idCiudadDestino,
                'idLugar'         => $request->idLugar,
            ]);

            $rutaVuelta = Ruta::create([
                'distancia'       => $request->distancia,
                'latitud'         => $request->latitud,
                'longitud'        => $request->longitud,
                'tiempoEstimado'  => $request->tiempoEstimado,
                'descripcion'     => "Ruta de vuelta: " . $request->descripcion,
                'precio'          => $request->precio,
                'idCiudadOrigen'  => $request->idCiudadDestino,
                'idCiudadDestino' => $request->idCiudadOrigen,
                'idLugar'         => $request->idLugar,
            ]);

            $rutaIda->update(['idRutaVuelta' => $rutaVuelta->id]);
            $rutaVuelta->update(['idRutaVuelta' => $rutaIda->id]);

            $viajeIda = ViajeController::store(new Request([
                'idVehiculo'  => null,
                'idConductor' => null,
                'idRuta'      => $rutaIda->id,
            ]))->getData();

            $viajeVuelta = ViajeController::store(new Request([
                'idVehiculo'  => null,
                'idConductor' => null,
                'idRuta'      => $rutaVuelta->id,
            ]))->getData();

            $user = KeyUtil::user();

            EstadoViaje::create([
                'estado'  => 'PENDIENTE',
                'tiempo'  => '00:00:00',
                'idViaje' => $viajeIda->id,
                'idUser'  => $user->id ?? null,
            ]);

            EstadoViaje::create([
                'estado'  => 'PENDIENTE',
                'tiempo'  => '00:00:00',
                'idViaje' => $viajeVuelta->id,
                'idUser'  => $user->id ?? null,
            ]);

            $rutaIda->viaje = $viajeIda;
            $rutaVuelta->viaje = $viajeVuelta;

            DB::commit();

            return response()->json(['rutaIda' => $rutaIda, 'rutaVuelta' => $rutaVuelta], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Create subruta by parent ruta
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse|mixed
     */
    public function createSubRuta(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $subRuta = Ruta::create([
                'distancia'       => $request->distancia,
                'latitud'         => $request->latitud,
                'longitud'        => $request->longitud,
                'tiempoEstimado'  => $request->tiempoEstimado,
                'descripcion'     => $request->descripcion,
                'precio'          => $request->precio,
                'idCiudadOrigen'  => $request->idCiudadOrigen ?? null,
                'idCiudadDestino' => $request->idCiudadDestino ?? null,
                'idLugar'         => $request->idLugar,
                'idRutaPadre'     => $request->idRutaPadre,
            ]);

            // ViajeController::store(new Request([
            //     'idVehiculo'  => null,
            //     'idConductor' => null,
            //     'idRuta'      => $subRuta->id,
            // ]))->getData();

            DB::commit();

            return response()->json($subRuta);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Ruta  $ruta
     * @return \Illuminate\Http\Response
     */
    public function show(string|int $id): JsonResponse
    {
        $ruta = Ruta::findOrFail($id);
        return response()->json($ruta);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Ruta  $ruta
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string|int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $ruta = Ruta::findOrFail($id);
            $ruta->update($request->all());

            if ($ruta->idRutaVuelta) {
                $rutaVuelta = Ruta::find($ruta->idRutaVuelta);
                if ($rutaVuelta) {
                    $rutaVuelta->update([
                        'distancia'       => $request->distancia,
                        'latitud'         => $request->latitud,
                        'longitud'        => $request->longitud,
                        'tiempoEstimado'  => $request->tiempoEstimado,
                        'descripcion'     => "Ruta de vuelta: " . ($request->descripcion ?? $ruta->descripcion),
                        'precio'          => $request->precio,
                        'idCiudadOrigen'  => $request->idCiudadDestino,
                        'idCiudadDestino' => $request->idCiudadOrigen,
                        'idLugar'         => $request->idLugar,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['ruta' => $ruta, 'rutaVuelta' => $rutaVuelta ?? null], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update subruta by id
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function updateSubRuta(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $subRuta = Ruta::findOrFail($id);

            $subRuta->update([
                'distancia'       => $request->distancia,
                'latitud'         => $request->latitud,
                'longitud'        => $request->longitud,
                'tiempoEstimado'  => $request->tiempoEstimado,
                'descripcion'     => $request->descripcion,
                'precio'          => $request->precio,
                'idCiudadOrigen'  => $request->idCiudadOrigen ?? null,
                'idCiudadDestino' => $request->idCiudadDestino ?? null,
                'idLugar'         => $request->idLugar,
                // 'idRutaPadre'     => $request->idRutaPadre,
            ]);

            DB::commit();

            return response()->json($subRuta);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Ruta  $ruta
     * @return \Illuminate\Http\Response
     */
    public function destroy(string|int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $ruta = Ruta::findOrFail($id);
            $idRutaVuelta = $ruta->idRutaVuelta;

            if ($ruta->rutasHijas()->exists()) {
                return response()->json(['message' => 'No se puede eliminar la ruta porque tiene sub rutas añadidas'], 400);
            }

            $ruta->update(['idRutaVuelta' => null]);

            if ($idRutaVuelta) {
                $rutaVuelta = Ruta::find($idRutaVuelta);
                if ($rutaVuelta && $rutaVuelta->rutasHijas()->exists()) {
                    return response()->json(['message' => 'No se puede eliminar la ruta porque tiene sub rutas añadidas'], 400);
                } else {
                    $rutaVuelta->update(['idRutaVuelta' => null]);
                    $rutaVuelta->delete();
                }
            }

            $ruta->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete sub ruta
     * @param string $id
     * @return JsonResponse
     */
    public function deleteSubRuta(string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $subRuta = Ruta::findOrFail($id);
            $subRuta->delete();

            DB::commit();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
