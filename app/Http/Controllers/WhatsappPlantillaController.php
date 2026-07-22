<?php

namespace App\Http\Controllers;

use App\Models\WhatsappPlantilla;
use Illuminate\Http\Request;

class WhatsappPlantillaController extends Controller
{
    /**
     * Get list of all templates.
     */
    public function index()
    {
        try {
            $plantillas = WhatsappPlantilla::orderBy('nombre', 'asc')->get();
            return response()->json($plantillas, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las plantillas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create a new template.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nombre' => ['required', 'string', 'unique:whatsappPlantillas,nombre'],
                'mensaje' => ['required', 'string']
            ]);

            $plantilla = WhatsappPlantilla::create([
                'nombre' => trim($data['nombre']),
                'mensaje' => trim($data['mensaje'])
            ]);

            return response()->json([
                'message' => 'Plantilla creada con éxito',
                'plantilla' => $plantilla
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear la plantilla: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a template.
     */
    public function destroy($id)
    {
        try {
            $plantilla = WhatsappPlantilla::findOrFail($id);
            $plantilla->delete();

            return response()->json(['message' => 'Plantilla eliminada correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar la plantilla: ' . $e->getMessage()], 500);
        }
    }
}
