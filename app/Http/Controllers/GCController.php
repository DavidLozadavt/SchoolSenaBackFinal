<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\GC;
use App\Models\DocumentoGC;
use App\Models\NotificacionSistema;
use App\Models\User;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

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
        DB::beginTransaction();
        try {
            $documento = DocumentoGC::findOrFail($id);
            $user = KeyUtil::user();
            $gc = GC::with('documentosGC')->findOrFail($documento->idGC);
            $contrato = Contract::where('id', $gc->idContrato)->with('persona')->first();
            $receptor = User::where('idpersona', $contrato->persona->id)->first();

            $documento->update([
                'estado' => 'ACEPTADO',
                'observacion' => null,
            ]);

            $gc->load('documentosGC');
            $this->actualizarEstadoGC($gc);

            // Correo
            $nombre = $contrato->persona->nombre1;
            $apellido = $contrato->persona->apellido1;
            $asunto = 'Aprobación de documento - ' . $documento->nombreDocumento;
            $mensaje = "Estimado(a) $nombre $apellido,\n\n"
                . "Nos complace informarle que su documento \"{$documento->nombreDocumento}\" ha sido APROBADO.\n\n"
                . "No se requieren acciones adicionales por su parte.\n\n"
                . "Si tiene alguna inquietud, puede comunicarse con el equipo administrativo.\n\n"
                . "Atentamente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            \App\Jobs\SendBasicEmail::dispatch($contrato->persona->email, $asunto, $mensaje);

            $this->enviarNotificacionDocumento($user->id, $receptor->id, $documento->nombreDocumento, null, 'ACEPTADO');

            DB::commit();
            return response()->json(['message' => 'Documento aceptado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aceptar documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al aceptar documento'], 500);
        }
    }

    public function rechazarDocumento(Request $request, $id)
    {
        $request->validate([
            'motivo' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $documento = DocumentoGC::findOrFail($id);
            $user = KeyUtil::user();
            $gc = GC::with('documentosGC')->findOrFail($documento->idGC);
            $contrato = Contract::where('id', $gc->idContrato)->with('persona')->first();
            $receptor = User::where('idpersona', $contrato->persona->id)->first();

            $documento->update([
                'estado' => 'RECHAZADO',
                'observacion' => $request->motivo,
            ]);


            $gc->load('documentosGC');
            $this->actualizarEstadoGC($gc);

            // Correo
            $nombre = $contrato->persona->nombre1;
            $apellido = $contrato->persona->apellido1;
            $asunto = 'Rechazo de documento - ' . $documento->nombreDocumento;
            $mensaje = "Estimado(a) $nombre $apellido,\n\n"
                . "Le informamos que su documento \"{$documento->nombreDocumento}\" ha sido RECHAZADO.\n\n"
                . "Motivo: {$request->motivo}\n\n"
                . "Por favor, cargue nuevamente el documento corregido.\n\n"
                . "Atentamente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            \App\Jobs\SendBasicEmail::dispatch($contrato->persona->email, $asunto, $mensaje);

            $this->enviarNotificacionDocumento($user->id, $receptor->id, $documento->nombreDocumento, $request->motivo, 'RECHAZADO');

            DB::commit();
            return response()->json(['message' => 'Documento rechazado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al rechazar documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al rechazar documento'], 500);
        }
    }

    public function revertirDocumento($id)
    {
        DB::beginTransaction();
        try {
            $documento = DocumentoGC::findOrFail($id);
            $user = KeyUtil::user();
            $gc = GC::with('documentosGC')->findOrFail($documento->idGC);
            $contrato = Contract::where('id', $gc->idContrato)->with('persona')->first();
            $receptor = User::where('idpersona', $contrato->persona->id)->first();

            $documento->update([
                'estado' => 'PENDIENTE',
                'observacion' => null,
            ]);

            $gc->load('documentosGC');
            $this->actualizarEstadoGC($gc);

            // Correo
            $nombre = $contrato->persona->nombre1;
            $apellido = $contrato->persona->apellido1;
            $asunto = 'Documento revertido a pendiente - ' . $documento->nombreDocumento;
            $mensaje = "Estimado(a) $nombre $apellido,\n\n"
                . "Le informamos que su documento \"{$documento->nombreDocumento}\" ha sido revertido a estado PENDIENTE.\n\n"
                . "Por favor, espere mientras el equipo administrativo revisa su documentación.\n\n"
                . "Si tiene alguna inquietud, puede comunicarse con el equipo administrativo.\n\n"
                . "Atentamente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            \App\Jobs\SendBasicEmail::dispatch($contrato->persona->email, $asunto, $mensaje);

            $this->enviarNotificacionDocumento($user->id, $receptor->id, $documento->nombreDocumento, null, 'REVERTIDO');

            DB::commit();
            return response()->json(['message' => 'Documento revertido a pendiente']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al revertir documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al revertir documento'], 500);
        }
    }

    public function aceptarTodosDocumentos($idGC)
    {
        DB::beginTransaction();
        try {
            $gc = GC::with('documentosGC')->findOrFail($idGC);
            $user = KeyUtil::user();
            $contrato = Contract::where('id', $gc->idContrato)->with('persona')->first();
            $receptor = User::where('idpersona', $contrato->persona->id)->first();

            $pendientes = $gc->documentosGC->where('estado', 'PENDIENTE');

            DocumentoGC::where('idGC', $idGC)
                ->where('estado', 'PENDIENTE')
                ->update([
                    'estado' => 'ACEPTADO',
                    'observacion' => null,
                ]);

            // Recargar documentos actualizados para calcular estado del GC
            $gc->load('documentosGC');
            $this->actualizarEstadoGC($gc);

            // Notificar por cada documento aceptado
            foreach ($pendientes as $doc) {
                $this->enviarNotificacionDocumento($user->id, $receptor->id, $doc->nombreDocumento, null, 'ACEPTADO');
            }

            // Un solo correo resumen
            $nombre = $contrato->persona->nombre1;
            $apellido = $contrato->persona->apellido1;
            $cantidad = $pendientes->count();
            $asunto = 'Aprobación masiva de documentos';
            $mensaje = "Estimado(a) $nombre $apellido,\n\n"
                . "Le informamos que $cantidad documento(s) pendiente(s) han sido APROBADOS.\n\n"
                . "Si tiene alguna inquietud, puede comunicarse con el equipo administrativo.\n\n"
                . "Atentamente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            \App\Jobs\SendBasicEmail::dispatch($contrato->persona->email, $asunto, $mensaje);

            DB::commit();
            return response()->json(['message' => 'Todos los documentos pendientes han sido aceptados']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aceptar todos los documentos GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al aceptar todos los documentos'], 500);
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

            $nombreDocumento = $request->filled('nombreDocumento')
                ? $request->nombreDocumento
                : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $documento = DocumentoGC::create([
                'idGC' => $request->idGC,
                'nombreDocumento' => $nombreDocumento,
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

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            } else {
                Log::warning("Archivo no encontrado en disco al eliminar DocumentoGC ID {$id}: {$path}");
            }

            $documento->delete();
            return response()->json(['message' => 'Documento eliminado']);
        } catch (\Exception $e) {
            Log::error('Error al eliminar documento GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar documento'], 500);
        }
    }

    public function descargarZip($id)
    {
        try {
            $gc = GC::with('documentosGC')->findOrFail($id);
            $documentos = $gc->documentosGC;
            $contrato = Contract::where('id', $gc->idContrato)->with('persona')->first();

            if ($documentos->isEmpty()) {
                return response()->json(['message' => 'No hay documentos para descargar'], 404);
            }

            $fileName = 'GC_' . $contrato->persona->identificacion . '_' . $contrato->siif . '_'
                . Carbon::now()->format('F_Y') . '.zip';

            $tempFile = tempnam(sys_get_temp_dir(), 'zip');
            $zip = new ZipArchive;

            if ($zip->open($tempFile, ZipArchive::CREATE) !== TRUE) {
                return response()->json(['message' => 'No se pudo crear el archivo ZIP'], 500);
            }

            foreach ($documentos as $doc) {
                $path = str_replace('/storage/', '', $doc->urlDocumento);
                if (Storage::disk('public')->exists($path)) {
                    $fullPath = storage_path('app/public/' . $path);
                    $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
                    $nombreEnZip = ($doc->nombreDocumento ?: 'documento_' . $doc->id) . '.' . $extension;
                    $zip->addFile($fullPath, $nombreEnZip);
                } else {
                    Log::warning("Archivo no encontrado al generar ZIP, DocumentoGC ID {$doc->id}: {$path}");
                }
            }

            $zip->close();

            return response()->download($tempFile, $fileName, [
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error al descargar ZIP de GC: ' . $e->getMessage());
            return response()->json(['message' => 'Error al generar el archivo ZIP'], 500);
        }
    }

    // ─── Métodos privados ────────────────────────────────────────────────────────

    private function actualizarEstadoGC(GC $gc)
    {
        $documentos = $gc->documentosGC;

        if ($documentos->isEmpty()) {
            $gc->update(['estado' => 'PENDIENTE']);
            return;
        }

        if ($documentos->contains('estado', 'RECHAZADO')) {
            $gc->update(['estado' => 'RECHAZADO']);
        } elseif ($documentos->every(fn($d) => $d->estado === 'ACEPTADO')) {
            $gc->update(['estado' => 'ACEPTADO']);
        } else {
            $gc->update(['estado' => 'PENDIENTE']);
        }
    }

    private function enviarNotificacionDocumento($remitenteId, $receptorId, $documento, $motivo = null, $tipo = 'ACEPTADO')
    {
        $asuntos = [
            'ACEPTADO' => 'Documento aprobado',
            'RECHAZADO' => 'Documento rechazado',
            'REVERTIDO' => 'Documento revertido a pendiente',
        ];

        $mensajes = [
            'ACEPTADO' => "Su documento \"{$documento}\" ha sido aprobado.",
            'RECHAZADO' => "Su documento \"{$documento}\" ha sido rechazado. Motivo: {$motivo}",
            'REVERTIDO' => "Su documento \"{$documento}\" ha sido revertido a estado pendiente.",
        ];

        return NotificacionSistema::create([
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
            'asunto' => $asuntos[$tipo] ?? 'Estado del documento',
            'mensaje' => $mensajes[$tipo] ?? "Estado actualizado para el documento \"{$documento}\".",
            'estado_id' => 1,
            'idUsuarioReceptor' => $receptorId,
            'idUsuarioRemitente' => $remitenteId,
            'idTipoNotificacion' => 1,
            'idEmpresa' => KeyUtil::idCompany(),
            'route' => '/gc',
        ]);
    }
}
