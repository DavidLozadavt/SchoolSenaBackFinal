<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Models\Vehiculo;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Carbon\Carbon;


class TurnoController extends Controller
{
    public function index()
    {
        $turnos = Turno::with(['conductor', 'vehiculo'])->get();
        return response()->json($turnos);
    }

    public function show($id)
    {
        $turno = Turno::with(['conductor', 'vehiculo'])->findOrFail($id);
        return response()->json($turno);
    }

    public function store(Request $request)
    {
        $request->validate([
            'idVehiculo'  => 'required|exists:vehiculo,id',
            'estado'      => 'in:PENDIENTE,AGENDADO,EN VIAJE,FINALIZADO,PLANILLA,CANCELADO',
        ]);

        $idConductor = KeyUtil::user()->idpersona;
        $now = Carbon::now();

        $fechaTurno = $now->toDateString();
        $horaInicio = $now->toTimeString();
        $horaFin    = $now->copy()->addHours(8)->toTimeString();

        $vehiculoAsignado = Vehiculo::where('id', $request->idVehiculo)
            ->whereHas('asignacionPropietario', function ($q) use ($idConductor) {
                $q->whereHas('afiliacion.conductor', function ($sub) use ($idConductor) {
                    $sub->where('idConductor', $idConductor)
                        ->where('estado', 'ACTIVO');
                })
                    ->where('estado', 'ACTIVO');
            })
            ->exists();

        if (!$vehiculoAsignado) {
            return response()->json([
                'message' => 'El vehículo no está asignado al conductor logueado o no está activo.'
            ], 422);
        }

        $existeCruce = Turno::where('idVehiculo', $request->idVehiculo)
            ->where('fechaTurno', $fechaTurno)
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->whereBetween('horaInicio', [$horaInicio, $horaFin])
                    ->orWhereBetween('horaFin', [$horaInicio, $horaFin])
                    ->orWhere(function ($sub) use ($horaInicio, $horaFin) {
                        $sub->where('horaInicio', '<=', $horaInicio)
                            ->where('horaFin', '>=', $horaFin);
                    });
            })
            ->whereNotIn('estado', ['CANCELADO', 'FINALIZADO'])
            ->exists();

        if ($existeCruce) {
            return response()->json([
                'message' => 'El vehículo ya tiene un turno asignado en ese horario.'
            ], 422);
        }

        $turno = Turno::create([
            'idConductor' => $idConductor,
            'idVehiculo'  => $request->idVehiculo,
            'fechaTurno'  => $fechaTurno,
            'horaInicio'  => $horaInicio,
            // 'horaFin'     => $horaFin,
            'estado'      => $request->estado ?? 'ACTIVO',
        ]);

        return response()->json([
            'message' => 'Turno iniciado correctamente',
            'data' => $turno
        ], 201);
    }




    public function update(Request $request, $id)
    {
        $turno = Turno::findOrFail($id);

        if ($turno->idConductor !== KeyUtil::user()->idpersona) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'idVehiculo'  => 'sometimes|exists:vehiculo,id',
            'fechaTurno'  => 'sometimes|date',
            'horaInicio'  => 'sometimes|date_format:H:i',
            'horaFin'     => 'sometimes|date_format:H:i|after:horaInicio',
            'estado'      => 'sometimes|in:PENDIENTE,AGENDADO,EN VIAJE,FINALIZADO,PLANILLA,CANCELADO',
        ]);

        $turno->update($request->all());

        return response()->json([
            'message' => 'Turno actualizado correctamente',
            'data' => $turno
        ]);
    }


    public function turnoActualConductor()
    {
        $idConductor = KeyUtil::user()->idpersona;
        $ahora = now()->format('H:i');
        $hoy = now()->toDateString();

        $turno = Turno::with([
            'vehiculo.asignacionPropietarios.afiliacion' => function ($q) {
                $q->select('id', 'numero');
            },
        ])
            ->where('idConductor', $idConductor)
            ->where('fechaTurno', $hoy)
            // ->where('horaInicio', '<=', $ahora)
            // ->where('horaFin', '>=', $ahora)
            ->whereNotIn('estado', ['CANCELADO', 'FINALIZADO'])
            ->first();


        if (!$turno) {
            return response()->json([
                'message' => 'No tienes un turno activo en este momento'
            ], 404);
        }

        return response()->json($turno);
    }




    public function finalizarTurno($id)
    {
        $turno = Turno::findOrFail($id);

        if ($turno->idConductor !== KeyUtil::user()->idpersona) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (in_array($turno->estado, ['FINALIZADO', 'CANCELADO'])) {
            return response()->json(['message' => 'El turno ya está finalizado o cancelado'], 422);
        }

        $turno->update([
            'horaFin' => now()->format('H:i'),
            'estado'  => 'FINALIZADO',
        ]);

        return response()->json([
            'message' => 'Turno finalizado correctamente',
            'data' => $turno
        ]);
    }



    public function destroy($id)
    {
        $turno = Turno::findOrFail($id);
        $turno->delete();

        return response()->json([
            'message' => 'Turno eliminado correctamente'
        ]);
    }
}
