<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionMantenimiento;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class ConfiguracionMantenimientoController extends Controller
{
    /**
     * Muestra la lista de configuraciones de mantenimiento.
     */
    public function index()
    {
        $idCompany = KeyUtil::idCompany();

        $configuraciones = ConfiguracionMantenimiento::where('idCompany', $idCompany)->get();

        return response()->json([
            'success' => true,
            'data' => $configuraciones
        ]);
    }

    /**
     * Guarda una nueva configuración.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'periodoRevisionSalida' => 'required|in:PORVIAJE,PORDIA',
        ]);

        $configuracion = ConfiguracionMantenimiento::create([
            'periodoRevisionSalida' => $validated['periodoRevisionSalida'],
            'idCompany' => KeyUtil::idCompany(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configuración creada correctamente.',
            'data' => $configuracion
        ]);
    }

    /**
     * Muestra una configuración específica.
     */
    public function show($id)
    {
        $configuracion = ConfiguracionMantenimiento::find($id);

        if (!$configuracion) {
            return response()->json([
                'success' => false,
                'message' => 'Configuración no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $configuracion
        ]);
    }

    /**
     * Actualiza una configuración existente.
     */
    public function update(Request $request, $id)
    {
        $configuracion = ConfiguracionMantenimiento::find($id);

        if (!$configuracion) {
            return response()->json([
                'success' => false,
                'message' => 'Configuración no encontrada.'
            ], 404);
        }

        $validated = $request->validate([
            'periodoRevisionSalida' => 'required|in:PORVIAJE,PORDIA',
        ]);

        $configuracion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada correctamente.',
            'data' => $configuracion
        ]);
    }

    /**
     * Elimina una configuración.
     */
    public function destroy($id)
    {
        $configuracion = ConfiguracionMantenimiento::find($id);

        if (!$configuracion) {
            return response()->json([
                'success' => false,
                'message' => 'Configuración no encontrada.'
            ], 404);
        }

        $configuracion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Configuración eliminada correctamente.'
        ]);
    }
}
