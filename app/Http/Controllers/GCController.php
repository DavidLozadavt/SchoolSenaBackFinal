<?php

namespace App\Http\Controllers;

use App\Models\GC;
use App\Models\DocumentoGC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GCController extends Controller
{
    public function crear(Request $request)
    {
        $request->validate([
            'idContrato' => 'required|integer|exists:contrato,id',
            'idRmi' => 'required|integer|exists:rmi,id',
        ]);

        try {
            $gc = GC::firstOrCreate([
                'idContrato' => $request->idContrato,
                'idRmi' => $request->idRmi,
            ], [
                'estado' => 'PENDIENTE',
            ]);

            return response()->json($gc, 201);
        } catch (\Exception $e) {
            Log::error('Error al crear GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear GC'], 500);
        }
    }

    public function getDocumentos($id)
    {
        try {
            $documentos = DocumentoGC::where('idGC', $id)->get();
            return response()->json($documentos);
        } catch (\Exception $e) {
            Log::error('Error al obtener documentos GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener documentos'], 500);
        }
    }

    public function aceptarDocumento($id)
    {
        try {
            $documento = DocumentoGC::findOrFail($id);
            $documento->update([
                'estado' => 'ACEPTADO',
                'observacion' => null
            ]);

            $this->actualizarEstadoGC($documento->idGC);

            return response()->json(['message' => 'Documento aceptado']);
        } catch (\Exception $e) {
            Log::error('Error al aceptar documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al aceptar documento'], 500);
        }
    }

    public function rechazarDocumento(Request $request, $id)
    {
        $request->validate([
            'motivo' => 'required|string',
        ]);

        try {
            $documento = DocumentoGC::findOrFail($id);
            $documento->update([
                'estado' => 'RECHAZADO',
                'observacion' => $request->motivo
            ]);

            $this->actualizarEstadoGC($documento->idGC);

            return response()->json(['message' => 'Documento rechazado']);
        } catch (\Exception $e) {
            Log::error('Error al rechazar documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al rechazar documento'], 500);
        }
    }

    public function subirDocumento(Request $request)
    {
        $request->validate([
            'idGC' => 'required|integer|exists:gC,id',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png',
        ]);

        try {
            $file = $request->file('archivo');
            $path = $file->store('gc_documentos', 'public');

            $documento = DocumentoGC::create([
                'idGC' => $request->idGC,
                'nombreDocumento' => $request->nombreDocumento ?? $file->getClientOriginalName(),
                'estado' => 'PENDIENTE',
                'urlDocumento' => '/storage/' . $path,
            ]);

            return response()->json($documento, 201);
        } catch (\Exception $e) {
            Log::error('Error al subir documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al subir documento'], 500);
        }
    }

    public function eliminarDocumento($id)
    {
        try {
            $documento = DocumentoGC::findOrFail($id);
            $path = str_replace('/storage/', '', $documento->urlDocumento);
            // Eliminar archivo de almacenamiento
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $documento->delete();
            return response()->json(['message' => 'Documento eliminado']);
        } catch (\Exception $e) {
            Log::error('Error al eliminar documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar documento'], 500);
        }
    }

    public function revertirDocumento($id)
    {
        try {
            $documento = DocumentoGC::findOrFail($id);
            $documento->update([
                'estado' => 'PENDIENTE',
                'observacion' => null
            ]);

            $this->actualizarEstadoGC($documento->idGC);

            return response()->json(['message' => 'Documento revertido a pendiente']);
        } catch (\Exception $e) {
            Log::error('Error al revertir documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al revertir documento'], 500);
        }
    }

    public function aceptarTodosDocumentos($idGC)
    {
        try {
            DocumentoGC::where('idGC', $idGC)
                ->where('estado', 'PENDIENTE')
                ->update([
                    'estado' => 'ACEPTADO',
                    'observacion' => null
                ]);

            $this->actualizarEstadoGC($idGC);

            return response()->json(['message' => 'Todos los documentos pendientes han sido aceptados']);
        } catch (\Exception $e) {
            Log::error('Error al aceptar todos los documentos GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al aceptar todos los documentos'], 500);
        }
    }

    private function actualizarEstadoGC($idGC)
    {
        $gc = GC::with('documentosGC')->findOrFail($idGC);
        $documentos = $gc->documentosGC;

        if ($documentos->isEmpty()) {
            $gc->update(['estado' => 'PENDIENTE']);
            return;
        }

        if ($documentos->contains('estado', 'RECHAZADO')) {
            $gc->update(['estado' => 'RECHAZADO']);
        } elseif ($documentos->every('estado', 'ACEPTADO')) {
            $gc->update(['estado' => 'ACEPTADO']);
        } else {
            $gc->update(['estado' => 'PENDIENTE']);
        }
    }
}
