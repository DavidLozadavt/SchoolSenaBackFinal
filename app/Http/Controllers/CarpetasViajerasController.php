<?php

namespace App\Http\Controllers;

use App\Models\ciadet\Archivos;
use App\Models\ciadet\CarpetasViajeras;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CarpetasViajerasController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    //  CARPETAS VIAJERAS
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /carpetas-viajeras */
    public function index(Request $request)
    {
        $query = \App\Models\ciadet\CarpetasViajeras::with(['persona', 'archivos']);

        if ($request->has('persona_id')) {
            $query->where('persona_id', $request->persona_id);
        }

        if ($request->has('pago')) {
            $query->where('pago', $request->pago);
        }

        if ($request->has('nivel_academico')) {
            $query->where('nivel_academico', $request->nivel_academico);
        }

        return response()->json($query->get());
    }

    /** POST /carpetas-viajeras */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'total' => 'nullable|numeric|min:0',
            'nivel_academico' => 'required|in:BACHILLERATO,TECNICO,TECNOLOGO',
            'modulo' => 'required|string|max:45',
            'aprobada' => 'nullable|boolean',
            'pago' => 'nullable|in:PENDIENTE,ENTREGADO,CANCELADO',
            'total_horas' => 'required|integer|min:0',
            'persona_id' => 'required|exists:persona,id',
            'codigo_transferencia' => 'nullable|string|max:45',
        ]);

        $carpeta = \App\Models\ciadet\CarpetasViajeras::create($validated);

        return response()->json($carpeta->load(['persona', 'archivos']), 201);
    }

    /** GET /carpetas-viajeras/{id} */
    public function show($id)
    {
        $carpeta = \App\Models\ciadet\CarpetasViajeras::with(['persona', 'archivos'])
            ->findOrFail($id);

        return response()->json($carpeta);
    }

    /** PUT/PATCH /carpetas-viajeras/{id} */
    public function update(Request $request, $id)
    {
        $carpeta = \App\Models\ciadet\CarpetasViajeras::findOrFail($id);

        $validated = $request->validate([
            'total' => 'nullable|numeric|min:0',
            'nivel_academico' => 'nullable|in:BACHILLERATO,TECNICO,TECNOLOGO',
            'modulo' => 'nullable|string|max:45',
            'aprobada' => 'nullable|boolean',
            'pago' => 'nullable|in:PENDIENTE,ENTREGADO,CANCELADO',
            'total_horas' => 'nullable|integer|min:0',
            'persona_id' => 'nullable|exists:persona,id',
            'codigo_transferencia' => 'nullable|string|max:45',
        ]);

        $carpeta->update($validated);

        return response()->json($carpeta->load(['persona', 'archivos']));
    }

    /** DELETE /carpetas-viajeras/{id} */
    public function destroy($id)
    {
        $carpeta = \App\Models\ciadet\CarpetasViajeras::with('archivos')->findOrFail($id);

        // Borrado por lógica de negocio: eliminar archivo físico y su registro en la BD uno a uno
        foreach ($carpeta->archivos as $archivo) {
            if ($archivo->urlArchivo) {
                Storage::disk('public')->delete($archivo->urlArchivo);
            }
            $archivo->delete();
        }

        // Eliminar el registro de la carpeta viajera
        $carpeta->delete();

        return response()->json(['message' => 'Carpeta viajera eliminada correctamente']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ARCHIVOS (sub-recursos de una carpeta viajera)
    // ─────────────────────────────────────────────────────────────────────────

    /** POST /carpetas-viajeras/{id}/archivos  — sube un archivo */
    public function storeArchivo(Request $request, $id)
    {
        $carpeta = \App\Models\ciadet\CarpetasViajeras::findOrFail($id);

        $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,xlsx,xls|max:10240',
            'aprobada' => 'nullable|boolean',
        ]);

        $file = $request->file('archivo');
        $originalName = $file->getClientOriginalName();
        $path = $file->store(Archivos::RUTA_ARCHIVO, 'public');

        // El campo `id` de archivos es manual (la PK compuesta usa id + carpetas_viajeras_id)
        // Generamos un id secuencial simple basado en el máximo existente para esa carpeta.
        $nextId = Archivos::where('carpetas_viajeras_id', $carpeta->id)->max('id') + 1;

        $archivo = Archivos::create([
            'id' => $nextId,
            'urlArchivo' => $path,
            'nombreArchivo' => $originalName,
            'aprobada' => $request->boolean('aprobada', false),
            'carpetas_viajeras_id' => $carpeta->id,
        ]);

        $this->recalcularAprobacionCarpeta($carpeta->id);

        return response()->json($archivo, 201);
    }

    /** PUT /carpetas-viajeras/{id}/archivos/{archivoId} — actualiza aprobada */
    public function updateArchivo(Request $request, $id, $archivoId)
    {
        $archivo = Archivos::where('carpetas_viajeras_id', $id)
            ->where('id', $archivoId)
            ->firstOrFail();

        $request->validate([
            'aprobada' => 'required|boolean',
        ]);

        $archivo->update(['aprobada' => $request->boolean('aprobada')]);

        $this->recalcularAprobacionCarpeta($id);

        return response()->json($archivo);
    }

    /** DELETE /carpetas-viajeras/{id}/archivos/{archivoId} */
    public function destroyArchivo($id, $archivoId)
    {
        $archivo = Archivos::where('carpetas_viajeras_id', $id)
            ->where('id', $archivoId)
            ->firstOrFail();

        if ($archivo->urlArchivo) {
            Storage::disk('public')->delete($archivo->urlArchivo);
        }

        $archivo->delete();

        $this->recalcularAprobacionCarpeta($id);

        return response()->json(['message' => 'Archivo eliminado correctamente']);
    }

    /**
     * Recalcula si todos los archivos de la carpeta viajera están aprobados
     * y actualiza el campo `aprobada` de la carpeta.
     */
    private function recalcularAprobacionCarpeta($carpetaId)
    {
        $carpeta = CarpetasViajeras::with('archivos')->find($carpetaId);
        if (!$carpeta) {
            return;
        }

        $totalArchivos = $carpeta->archivos->count();
        $archivosAprobados = $carpeta->archivos->where('aprobada', true)->count();

        // Se aprueba automáticamente la carpeta solo si tiene al menos 1 archivo y todos están aprobados
        $todasAprobadas = ($totalArchivos > 0 && $archivosAprobados === $totalArchivos);

        $carpeta->update(['aprobada' => $todasAprobadas]);
    }
    public function verArchivo($id, $archivoId): StreamedResponse
    {
        $archivo = Archivos::where('carpetas_viajeras_id', $id)
            ->where('id', $archivoId)
            ->firstOrFail();

        $disk = Storage::disk('public');

        if (empty($archivo->urlArchivo) || !$disk->exists($archivo->urlArchivo)) {
            abort(404, 'Archivo no encontrado en el servidor');
        }

        $nombreOriginal = $archivo->nombreArchivo ?? basename($archivo->urlArchivo);
        $mimeType = $disk->mimeType($archivo->urlArchivo);

        $extensionesVisualizables = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'txt', 'csv', 'json', 'html'];
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $disposition = in_array($extension, $extensionesVisualizables) ? 'inline' : 'attachment';

        return $disk->response(
            $archivo->urlArchivo,
            $nombreOriginal,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . $nombreOriginal . '"',
            ]
        );
    }
}
