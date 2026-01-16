<?php

namespace App\Http\Controllers\gestion_transporte;

use App\Enums\EstadosViaje;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use App\Models\Transporte\Viaje;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Transporte\ObservacionViaje;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;


class ViajeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
 public function index(Request $request): JsonResponse
{
    $viajes = Viaje::with([
        'conductor.persona',
        'conductorAuxiliar.persona',
        'vehiculo.marca',
        'vehiculo.modelo',
        'vehiculo.tipoVehiculo',
        'vehiculo.asignacionPropietarios.afiliacion',
        'ruta.ciudadOrigen',
        'ruta.ciudadDestino',
        'agendarViajes',
        'tickets.ruta',
        'vehiculo.configuracionVehiculo',
        'vehiculo.revisionReciente',
    ])
    ->orderBy("id", "desc")
    ->paginate($request->totalPages ?? 20);

    $viajes->getCollection()->transform(function ($viaje) {
        $vehiculo = $viaje->vehiculo;
        $viaje->hasRevisionReciente = $vehiculo && $vehiculo->revisionReciente ? true : false;
        return $viaje;
    });

    return response()->json([
        'total'  => $viajes->total(),
        'viajes' => $viajes->items(),
    ]);
}



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

public static function store(Request $request): JsonResponse
{
    $user = KeyUtil::user();

    if (!$user) {
        return response()->json([
            'message' => 'Usuario no encontrado o se ha terminado tu sesión, por favor inicia sesión nuevamente'
        ], 422);
    }

    try {
        $viaje = DB::transaction(function () use ($request, $user) {
            $ultimo = Viaje::lockForUpdate()->max('numeroPlanillaViaje');

            $siguiente = $ultimo ? (int)$ultimo + 1 : 1;

            $numeroPlanillaViaje = str_pad($siguiente, 7, '0', STR_PAD_LEFT);

            $viaje = Viaje::create([
                'numeroPlanillaViaje' => $numeroPlanillaViaje,
                'estado' => EstadosViaje::PENDIENTE,
                ...$request->except(['estado', 'numeroPlanillaViaje'])
            ]);

            $observacion = ObservacionViaje::create([
                'observacion' => $request->observacion ?? 'Nuevo viaje creado',
                'idViaje'     => $viaje->id,
                'idUser'      => $user->id,
            ]);

            $viaje->observacion = $observacion;

            return $viaje;
        });

        return response()->json($viaje, 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al crear el viaje',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * Display the specified resource.
     *
     * @param  Viaje  $viaje
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $viaje = Viaje::findOrFail($id);
        return response()->json($viaje);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Viaje  $viaje
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
 {
    $viaje = Viaje::findOrFail($id);
    $nuevoVehiculoId = $request->idVehiculo ?? $viaje->idVehiculo;

    $agendas = DB::table('agendarviajes')
        ->where('idViaje', $viaje->id)
        ->get(['fecha', 'hora']);

    $conflicto = DB::table('agendarviajes as a')
        ->join('viajes as v', 'v.id', '=', 'a.idViaje')
        ->where('v.idVehiculo', $nuevoVehiculoId)
        ->where('a.idViaje', '!=', $viaje->id)
        ->where(function ($query) use ($agendas) {
            foreach ($agendas as $agenda) {
                $query->orWhere(function ($sub) use ($agenda) {
                    $sub->where('a.fecha', $agenda->fecha)
                        ->where('a.hora', $agenda->hora);
                });
            }
        })
        ->exists();

    if ($conflicto) {
        return response()->json([
            'message' => 'El vehículo seleccionado ya tiene un viaje programado en la misma fecha y hora.',
        ], 422);
    }

    $viaje->update($request->all());

    return response()->json([
        'message' => 'Viaje actualizado correctamente',
        'viaje' => $viaje,
    ], 200);
}


    /**
     * Update only conductor to trip
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return JsonResponse|mixed
     */
    public function updateDriver(Request $request, string $id): JsonResponse
{
    $request->validate([
        'idConductor' => [
            'nullable',
            'exists:contrato,id',
        ],
        'idConductorAuxiliar' => [
            'nullable',
            'exists:contrato,id',
        ],
        'observacion' => 'nullable|string',
    ], [
        'idConductor.exists' => 'El conductor no existe en la base de datos.',
        'idConductorAuxiliar.exists' => 'El conductor auxiliar no existe en la base de datos.',
        'observacion.string' => 'La observación debe ser una cadena de texto.',
    ]);

    $viaje = Viaje::findOrFail($id);

    $previousConductor = $viaje->conductor;
    $previousAux = $viaje->conductorAuxiliar;

   $idConductor = $request->has('idConductor') ? $request->input('idConductor') : $viaje->idConductor;
    $idAuxiliar = $request->has('idConductorAuxiliar') ? $request->input('idConductorAuxiliar') : $viaje->idConductorAuxiliar;


    $viaje->update([
        'idConductor' => $idConductor,
        'idConductorAuxiliar' => $idAuxiliar,
    ]);

    $newContract = Contract::find($idConductor);
    $newAux = Contract::find($idAuxiliar);

    $user = KeyUtil::user();
    if (!$user) {
        return response()->json(['message' => 'Usuario no encontrado o se ha terminado tu sesión, por favor inicia sesión nuevamente'], 422);
    }

    $changes = [];

    if ($previousConductor?->id !== $idConductor) {
        $changes[] = 'Conductor actualizado ' .
            ($previousConductor?->persona->nombre1 ?? 'N/A') . ' ' .
            ($previousConductor?->persona->apellido1 ?? '') . ' por ' .
            optional($newContract->persona)->nombre1 . ' ' .
            optional($newContract->persona)->apellido1;
    }

    if ($previousAux?->id !== $idAuxiliar) {
        $changes[] = 'Conductor auxiliar actualizado ' .
            ($previousAux?->persona->nombre1 ?? 'N/A') . ' ' .
            ($previousAux?->persona->apellido1 ?? '') . ' por ' .
            optional($newAux->persona)->nombre1 . ' ' .
            optional($newAux->persona)->apellido1;
    }

    $automaticObservation = implode(' | ', $changes);

    $observacion = ObservacionViaje::create([
        'observacion' => $request->observacion ?? $automaticObservation,
        'idViaje'     => $viaje->id,
        'idUser'      => $user->id,
    ]);

    $viaje->observacion = $observacion;

    return response()->json($viaje, 200);
}



    /**
     * Update only conductor to trip
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return JsonResponse|mixed
     */
    public function updateVehicle(Request $request, string $id): JsonResponse
    {

        $request->validate([
            'idVehiculo' => [
                'required',
                'exists:vehiculo,id',
            ],
            'observacion' => 'nullable|string',
        ], [
            'idVehiculo.required' => 'El campo del vehiculo es obligatorio.',
            'idVehiculo.exists'   => 'El vehiculo no existe en la base de datos.',
            'observacion.nullable' => 'La observación puede ser nula.',
            'observacion.string'   => 'La observación debe ser una cadena de texto.',
        ]);

        $viaje = Viaje::findOrFail($id);

        $idVehiculo = $request->input('idVehiculo');

        $viaje->update(['idVehiculo' => $idVehiculo]);

        $vehiculo = Vehiculo::find($idVehiculo);

        $user = KeyUtil::user();
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado o se ha terminado tu sesión, por favor inicia sesión nuevamente'], 422);
        }

        $automaticObservation = 'Vehiculo actualizado ' .
            'placa ' . $viaje->vehiculo->placa . ' modelo '  . $viaje->vehiculo->modelo->modelo . ' por el vehiculo ' .
            'placa ' . $vehiculo->placa        . ' modelo '  . $vehiculo->modelo->modelo;

        $observacion = ObservacionViaje::create([
            'observacion' => $request->observacion ?? $automaticObservation,
            'idViaje'     => $viaje->id,
            'idUser'      => $user->id,
        ]);

        $viaje->observacion = $observacion;

        return response()->json($viaje, 200);
    }

    public function removeVehicle(Request $request, string $id): JsonResponse
 {
    $viaje = Viaje::findOrFail($id);

    if (!$viaje->idVehiculo) {
        return response()->json([
            'message' => 'El viaje no tiene un vehículo asignado actualmente.'
        ], 400);
    }

    $vehiculoAnterior = $viaje->vehiculo;

    $viaje->update(['idVehiculo' => null]);

    $user = KeyUtil::user();
    if (!$user) {
        return response()->json([
            'message' => 'Usuario no encontrado o sesión expirada, por favor inicia sesión nuevamente.'
        ], 422);
    }

    $automaticObservation = 'Vehículo removido del viaje. ' .
        'Antes: placa ' . ($vehiculoAnterior->placa ?? 'N/A') .
        ', modelo ' . ($vehiculoAnterior->modelo->modelo ?? 'N/A');

    $observacion = ObservacionViaje::create([
        'observacion' => $request->observacion ?? $automaticObservation,
        'idViaje'     => $viaje->id,
        'idUser'      => $user->id,
    ]);

    $viaje->observacion = $observacion;

    return response()->json([
        'message' => 'Vehículo removido correctamente del viaje.',
        'viaje'   => $viaje,
    ], 200);
}

    /**
     * Remove the specified resource from storage.
     *
     * @param  Viaje  $viaje
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $viaje = Viaje::findOrFail($id);
        $viaje->delete();
        return response()->json();
    }
}
