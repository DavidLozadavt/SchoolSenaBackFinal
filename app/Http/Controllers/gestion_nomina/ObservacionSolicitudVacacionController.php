<?php

namespace App\Http\Controllers\gestion_nomina;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Nomina\ObservacionSolicitudVacacion;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;

class ObservacionSolicitudVacacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $observaciones = ObservacionSolicitudVacacion::with(['usuario.persona'])
            ->where('idSolicitud', $request->idSolicitud)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($observaciones);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $request->request->add(['fecha' => $request->fecha ?? now()]);
        $request->request->add(['idUsuario' => KeyUtil::user()->id]);
        $observacion = ObservacionSolicitudVacacion::create($request->all());
        return response()->json($observacion->load('usuario.persona'), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ObservacionSolicitudVacacion  $observacionSolicitudVacacion
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $observacion = ObservacionSolicitudVacacion::findOrFail($id);
        return response()->json($observacion);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ObservacionSolicitudVacacion  $observacionSolicitudVacacion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $observacion = ObservacionSolicitudVacacion::findOrFail($id);
        $observacion->update($request->all());
        return response()->json();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ObservacionSolicitudVacacion  $observacionSolicitudVacacion
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $observacion = ObservacionSolicitudVacacion::findOrFail($id);
        $observacion->delete();
        return response()->json(null, 204);
    }
}
