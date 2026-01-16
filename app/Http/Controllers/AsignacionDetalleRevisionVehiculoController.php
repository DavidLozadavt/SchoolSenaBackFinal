<?php

namespace App\Http\Controllers;

use App\Models\AsignacionDetalleRevisionVehiculo;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AsignacionDetalleRevisionVehiculoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AsignacionDetalleRevisionVehiculo::with([
            'detalleRevision',
            'vehiculo',
            'usuario',
            'viaje'
        ]);

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('idVehiculo')) {
            $query->where('idVehiculo', $request->idVehiculo);
        }

        $asignaciones = $query->get();

        return response()->json([
            'success' => true,
            'data' => $asignaciones
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idVehiculo' => 'required|exists:vehiculo,id',
            'idViaje' => 'nullable|exists:viajes,id',
            'detalles' => 'required|array',
            'detalles.*.idDetalle' => 'required|exists:detalleRevision,id',
            'detalles.*.estado' => 'nullable|in:ACTIVO,PENDIENTE,PORREVISION,RECHAZADO',
            'detalles.*.observacion' => 'nullable|string',
            'detalles.*.fechaLimite' => 'nullable|date',
        ]);

        $idUser = KeyUtil::user()->id;
        $creadas = [];

     foreach ($validated['detalles'] as $detalle) {
   
         $estadoOriginal = empty($detalle['estado']) ? 'PENDIENTE' : $detalle['estado'];
        $estadoFinal = $estadoOriginal === 'PENDIENTE' ? 'PORREVISION' : $estadoOriginal;


            $asignacion = AsignacionDetalleRevisionVehiculo::create([
                'idDetalle' => $detalle['idDetalle'],
                'idVehiculo' => $validated['idVehiculo'],
                'idViaje' => $validated['idViaje'] ?? null,
                'idTecnico' => $idUser,
                'estado' => $estadoFinal,
                'fechaRevision' => now(),
                'fechaLimite' => $detalle['fechaLimite'] ?? null,
                'observacion' => $detalle['observacion'] ?? null,
            ]);

            $creadas[] = $asignacion;
        }

        return response()->json([
            'success' => true,
            'message' => 'Revisión preoperacional registrada exitosamente',
            'data' => AsignacionDetalleRevisionVehiculo::with('detalleRevision')
                ->whereIn('id', collect($creadas)->pluck('id'))
                ->get()
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $asignacion = AsignacionDetalleRevisionVehiculo::with([
            'detalleRevision',
            'vehiculo',
            'usuario',
            'viaje'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $asignacion
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $asignacion = AsignacionDetalleRevisionVehiculo::findOrFail($id);

        $validated = $request->validate([
            'idDetalle' => 'nullable|exists:detalle_revision,id',
            'idVehiculo' => 'required|exists:vehiculo,id',
            'idUser' => 'required|exists:usuario,id',
            'idViaje' => 'required|exists:viajes,id',
            'fechaRevision' => 'nullable|date',
            'estado' => 'required|in:ACTIVO,INACTIVO,PENDIENTE',
        ]);

        $asignacion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Asignación actualizada exitosamente',
            'data' => $asignacion->load([
                'detalleRevision',
                'vehiculo',
                'usuario',
                'viaje'
            ])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $asignacion = AsignacionDetalleRevisionVehiculo::findOrFail($id);
        $asignacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asignación eliminada exitosamente'
        ]);
    }

    /**
     * Cambiar el estado de una asignación
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $asignacion = AsignacionDetalleRevisionVehiculo::findOrFail($id);

        $validated = $request->validate([
            'estado' => 'required|in:ACTIVO,INACTIVO,PENDIENTE',
        ]);

        $asignacion->update(['estado' => $validated['estado']]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'data' => $asignacion
        ]);
    }



    public function vehiculosConRechazos(): JsonResponse
    {
        $ultimasRevisiones = AsignacionDetalleRevisionVehiculo::selectRaw('idVehiculo, MAX(fechaRevision) as ultimaRevision')
            ->groupBy('idVehiculo')
            ->get();

        $mapaFechas = $ultimasRevisiones->pluck('ultimaRevision', 'idVehiculo');

        $asignaciones = AsignacionDetalleRevisionVehiculo::with(['vehiculo.marca', 'vehiculo.modelo', 'detalleRevision'])
            ->whereIn('estado', ['RECHAZADO', 'PORREVISION'])
            ->where(function ($query) use ($mapaFechas) {
                foreach ($mapaFechas as $idVehiculo => $fecha) {
                    $query->orWhere(function ($sub) use ($idVehiculo, $fecha) {
                        $sub->where('idVehiculo', $idVehiculo)
                            ->where('fechaRevision', $fecha);
                    });
                }
            })
            ->get()
            ->groupBy('idVehiculo')
            ->map(function ($items) {
                $vehiculo = $items->first()->vehiculo;

                $rechazos = $items->where('estado', 'RECHAZADO')->map(function ($r) {
                    return [
                        'detalle' => $r->detalleRevision->nombre ?? 'Sin nombre',
                        'observacion' => $r->observacion ?? 'Sin observación',
                        'fechaRevision' => optional($r->fechaRevision)->format('Y-m-d H:i:s'),
                    ];
                })->values();

                $porRevision = $items->where('estado', 'PORREVISION')->map(function ($r) {
                    return [
                        'detalle' => $r->detalleRevision->nombre ?? 'Sin nombre',
                        'observacion' => $r->observacion ?? 'Sin observación',
                        'fechaRevision' => optional($r->fechaRevision)->format('Y-m-d H:i:s'),
                    ];
                })->values();

                return [
                    'vehiculo' => [
                        'id' => $vehiculo->id,
                        'placa' => $vehiculo->placa,
                        'marca' => $vehiculo->marca,
                        'modelo' => $vehiculo->modelo,
                        'foto' => $vehiculo->foto ?? null,
                    ],
                    'rechazos' => $rechazos,
                    'porRevision' => $porRevision,
                    'tieneRechazo' => $rechazos->isNotEmpty(),
                    'tienePorRevision' => $porRevision->isNotEmpty(),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Vehículos con ítems rechazados o por revisión cargados correctamente (última revisión).',
            'data' => $asignaciones,
        ]);
    }



    public function ultimaRevisionCompleta($idVehiculo): JsonResponse
    {
        $ultimaFecha = AsignacionDetalleRevisionVehiculo::where('idVehiculo', $idVehiculo)
            ->max('fechaRevision');

        if (!$ultimaFecha) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron revisiones para este vehículo',
            ], 404);
        }

        $detalles = AsignacionDetalleRevisionVehiculo::with(['detalleRevision'])
            ->where('idVehiculo', $idVehiculo)
            ->where('fechaRevision', $ultimaFecha)
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Última revisión completa del vehículo obtenida correctamente',
            'fechaRevision' => $ultimaFecha,
            'data' => $detalles,
        ]);
    }


    public function revisionesRecientes($idVehiculo): JsonResponse
    {
        $fechas = AsignacionDetalleRevisionVehiculo::where('idVehiculo', $idVehiculo)
            ->select('fechaRevision')
            ->distinct()
            ->orderByDesc('fechaRevision')
            ->take(3)
            ->pluck('fechaRevision');

        if ($fechas->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron revisiones recientes para este vehículo',
            ], 404);
        }

        $revisiones = AsignacionDetalleRevisionVehiculo::with(['detalleRevision'])
            ->where('idVehiculo', $idVehiculo)
            ->whereIn('fechaRevision', $fechas)
            ->orderByDesc('fechaRevision')
            ->get()
            ->groupBy('fechaRevision');

        return response()->json([
            'success' => true,
            'message' => 'Revisiones recientes del vehículo obtenidas correctamente',
            'data' => $revisiones,
        ]);
    }
}
