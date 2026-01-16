<?php

namespace App\Http\Controllers;

use App\Models\EstadoViaje;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
class EstadoViajeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $estadoViajes = EstadoViaje::all()->load('user.persona');
        return response()->json($estadoViajes);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = KeyUtil::user();

        $segundosTotales = $request->input('tiempoTranscurrido', 0);
        $horas = floor($segundosTotales / 3600);
        $minutos = floor(($segundosTotales % 3600) / 60);
        $segundos = $segundosTotales % 60;
        $tiempoFormateado = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);

        $estadoViaje = EstadoViaje::updateOrCreate(
            ['idViaje' => $request->input('idViaje')], 
            [
                'estado' => $request->input('estado'),
                'idUser' => $user->id,
                'tiempo' => $tiempoFormateado,
            ]
        );

        return response()->json($estadoViaje, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\EstadoViaje  $estadoViaje
     * @return \Illuminate\Http\Response
     */
    public function show(EstadoViaje $estadoViaje)
    {
        $estadoViaje = EstadoViaje::findOrFail($estadoViaje);

        return response()->json($estadoViaje);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\EstadoViaje  $estadoViaje
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, EstadoViaje $estadoViaje)
    {
        $data = $request->all();

        $estadoViaje = EstadoViaje::findOrFail($estadoViaje);
        $estadoViaje->update($data);
        return response()->json($estadoViaje);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\EstadoViaje  $estadoViaje
     * @return \Illuminate\Http\Response
     */
    public function destroy(string|int $id): JsonResponse
    {
        $place = EstadoViaje::findOrFail($id);
        $place->delete();
        return response()->json(null, 204);
    }
}
