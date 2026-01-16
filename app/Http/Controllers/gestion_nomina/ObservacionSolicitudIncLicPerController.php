<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\ObservacionSolicitudIncLicPer;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObservacionSolicitudIncLicPerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $observaciones = ObservacionSolicitudIncLicPer::with(['usuario.persona'])
            ->where('idSolicitud', $request->idSolicitud)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($observaciones);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->request->add(['fecha' => $request->fecha ?? now()]);
        $request->request->add(['idUsuario' => KeyUtil::user()->id]);
        $observacion = ObservacionSolicitudIncLicPer::create($request->all());
        return response()->json($observacion, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  ObservacionSolicitudIncLicPer  $observacionSolicitudIncLicPer
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $observacion = ObservacionSolicitudIncLicPer::findOrFail($id);
        return response()->json($observacion);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  ObservacionSolicitudIncLicPer  $observacionSolicitudIncLicPer
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $observacion = ObservacionSolicitudIncLicPer::findOrFail($id);
        $observacion->update($request->all());
        return response()->json($observacion);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  ObservacionSolicitudIncLicPer  $observacionSolicitudIncLicPer
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $observacion = ObservacionSolicitudIncLicPer::findOrFail($id);
        $observacion->delete();
        return response()->json();
    }
}
