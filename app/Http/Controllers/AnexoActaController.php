<?php

namespace App\Http\Controllers;

use App\Models\AnexoActa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AnexoActaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg|max:10240', // Max 10MB
            'idacta' => 'nullable|exists:acta,id',
            'nombre' => 'nullable|string',
            'descripcion' => 'nullable|string',
        ]);

        try {
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $originalName = $file->getClientOriginalName();
                $nombreArchivo = uniqid('acta_anexo_') . '_' . $originalName;

                // Store in public/documentos/anexos_acta
                $path = $file->storeAs('public/documentos/anexos_acta', $nombreArchivo);
                $url = Storage::url($path);

                $data = [
                    'nombre' => $request->input('nombre') ?? $originalName,
                    'archivo' => $path,
                    'descripcion' => $request->input('descripcion'),
                    'idacta' => $request->input('idacta'),
                ];

                // If idacta is provided, create the record immediately
                if ($data['idacta']) {
                    $anexo = AnexoActa::create($data);
                    return response()->json([
                        'message' => 'Archivo subido y registrado correctamente',
                        'data' => $anexo
                    ], 201);
                }

                // If no idacta, just return the data (useful for preview or temp storage)
                return response()->json([
                    'message' => 'Archivo subido correctamente',
                    'data' => $data
                ], 200);
            }

            return response()->json(['message' => 'No se encontró ningún archivo'], 400);
        } catch (\Exception $e) {
            Log::error('Error al subir anexo de acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al subir el archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $anexo = AnexoActa::findOrFail($id);

            // Delete file from storage
            $path = str_replace('/storage/', 'public/', $anexo->archivo);
            Storage::delete($path);

            $anexo->delete();

            return response()->json([
                'message' => 'Anexo eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar anexo de acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar el anexo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
