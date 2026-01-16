<?php

namespace App\Http\Controllers;

use App\Models\AsignacionDescuentoPlanilla;
use App\Models\DescuentoPlanilla;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AsignacionDescuentoPlanillaController extends Controller
{
    /**
     * Listar asignaciones de descuentos (opcionalmente filtradas)
     */
    public function index(Request $request)
    {
        $idCompany = KeyUtil::idCompany();

        $query = AsignacionDescuentoPlanilla::whereHas('descuento', function ($q) use ($idCompany) {
            $q->where('idCompany', $idCompany);
        });

        if ($request->has('idViaje')) {
            $query->where('idViaje', $request->idViaje);
        }

        if ($request->has('idDescuento')) {
            $query->where('idDescuento', $request->idDescuento);
        }

        $asignaciones = $query->with(['descuento', 'viaje', 'taquillero'])->get();

        return response()->json([
            'success' => true,
            'data' => $asignaciones
        ], 200);
    }

    /**
     * Asignar uno o varios descuentos a un viaje
     */
    public function store(Request $request)
    {
        $idCompany = KeyUtil::idCompany();
        $idUser = KeyUtil::user()->id;

        $validator = Validator::make($request->all(), [
            'idViaje' => 'required|integer|exists:viajes,id',
            'descuentos' => 'required|array|min:1',
            'descuentos.*.idDescuento' => 'required|integer|exists:descuentosPlanilla,id',
            'descuentos.*.observacion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $idsDescuentos = collect($request->descuentos)->pluck('idDescuento');
        $descuentosValidos = DescuentoPlanilla::where('idCompany', $idCompany)
            ->whereIn('id', $idsDescuentos)
            ->pluck('id');

        if ($descuentosValidos->count() !== $idsDescuentos->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Algunos descuentos no pertenecen a su empresa'
            ], 403);
        }

        $asignaciones = [];
        $fecha = now();

        foreach ($request->descuentos as $descuento) {
            $existe = AsignacionDescuentoPlanilla::where('idDescuento', $descuento['idDescuento'])
                ->where('idViaje', $request->idViaje)
                ->first();

            if ($existe) {
                continue;
            }

            $asignacion = AsignacionDescuentoPlanilla::create([
                'idDescuento' => $descuento['idDescuento'],
                'idViaje' => $request->idViaje,
                'idTaquillero' => $idUser,
                'fecha' => $fecha,
                'valor' => $descuento['valor'] ?? 0
            ]);

            $asignaciones[] = $asignacion->load(['descuento', 'viaje', 'taquillero']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Descuentos asignados exitosamente',
            'data' => $asignaciones
        ], 201);
    }

    /**
     * Mostrar una asignación específica
     */
    public function show($id)
    {
        $idCompany = KeyUtil::idCompany();

        $asignacion = AsignacionDescuentoPlanilla::whereHas('descuento', function ($q) use ($idCompany) {
            $q->where('idCompany', $idCompany);
        })
        ->with(['descuento', 'viaje', 'taquillero'])
        ->find($id);

        if (!$asignacion) {
            return response()->json([
                'success' => false,
                'message' => 'Asignación no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $asignacion
        ], 200);
    }

    /**
     * Actualizar una asignación
     */
    public function update(Request $request, $id)
    {
        $idCompany = KeyUtil::idCompany();

        $asignacion = AsignacionDescuentoPlanilla::whereHas('descuento', function ($q) use ($idCompany) {
            $q->where('idCompany', $idCompany);
        })->find($id);

        if (!$asignacion) {
            return response()->json([
                'success' => false,
                'message' => 'Asignación no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'observacion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $asignacion->update($request->only(['observacion']));

        return response()->json([
            'success' => true,
            'message' => 'Asignación actualizada exitosamente',
            'data' => $asignacion->load(['descuento', 'viaje', 'taquillero'])
        ], 200);
    }

    /**
     * Eliminar una asignación
     */
    public function destroy($id)
    {
        $idCompany = KeyUtil::idCompany();

        $asignacion = AsignacionDescuentoPlanilla::whereHas('descuento', function ($q) use ($idCompany) {
            $q->where('idCompany', $idCompany);
        })->find($id);

        if (!$asignacion) {
            return response()->json([
                'success' => false,
                'message' => 'Asignación no encontrada'
            ], 404);
        }

        $asignacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asignación eliminada exitosamente'
        ], 200);
    }

    /**
     * Eliminar todas las asignaciones de un viaje
     */
    public function destroyByViaje($idViaje)
    {
        $idCompany = KeyUtil::idCompany();

        $deleted = AsignacionDescuentoPlanilla::whereHas('descuento', function ($q) use ($idCompany) {
            $q->where('idCompany', $idCompany);
        })
        ->where('idViaje', $idViaje)
        ->delete();

        return response()->json([
            'success' => true,
            'message' => "Se eliminaron {$deleted} asignaciones del viaje"
        ], 200);
    }

    /**
     * Resumen de descuentos por viaje
     */
    public function resumenViaje($idViaje)
    {
        $idCompany = KeyUtil::idCompany();

        $asignaciones = AsignacionDescuentoPlanilla::whereHas('descuento', function ($q) use ($idCompany) {
            $q->where('idCompany', $idCompany);
        })
        ->where('idViaje', $idViaje)
        ->with('descuento')
        ->get();

        $totalValor = $asignaciones->sum(fn($a) => $a->descuento->valor ?? 0);
        $totalPorcentaje = $asignaciones->sum(fn($a) => $a->descuento->porcentaje ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'asignaciones' => $asignaciones,
                'resumen' => [
                    'total_descuentos' => $asignaciones->count(),
                    'obligatorios' => $asignaciones->filter(fn($a) => $a->descuento->obligatorio)->count(),
                    'opcionales' => $asignaciones->filter(fn($a) => !$a->descuento->obligatorio)->count(),
                    'total_valor' => $totalValor,
                    'total_porcentaje' => $totalPorcentaje
                ]
            ]
        ], 200);
    }
}
