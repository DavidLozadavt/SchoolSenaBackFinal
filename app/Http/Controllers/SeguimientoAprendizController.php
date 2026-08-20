<?php

namespace App\Http\Controllers;

use App\Jobs\SendBasicEmail;
use App\Models\Contract;
use App\Models\SeguimientoAprendiz;
use App\Models\DocumentoSeguimiento;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SeguimientoAprendizController extends Controller
{
    const RUTA_DOCUMENTOS = 'seguimiento/documentos';

    // ══════════════════════════════════════════════════════════════
    //  SEGUIMIENTO
    // ══════════════════════════════════════════════════════════════

    public function index()
    {
        $seguimientos = SeguimientoAprendiz::with(['persona', 'contrato', 'documentos'])
            ->latest()
            ->paginate(15);

        return response()->json($seguimientos);
    }

    public function instructores()
    {
        $instructores = Contract::with('persona')->get();

        return response()->json($instructores);
    }

    /**
     * Busca el seguimiento de un aprendiz para un contrato específico.
     * Devuelve null si aún no existe (el frontend usa esto para decidir
     * si debe mostrar "Iniciar seguimiento" o la gestión ya existente).
     *
     * IMPORTANTE: registra esta ruta ANTES del apiResource('seguimientos', ...),
     * de lo contrario Laravel intentará bindear "buscar" como {seguimiento}.
     *   Route::get('seguimientos/buscar', [SeguimientoController::class, 'porAprendizContrato']);
     *   Route::apiResource('seguimientos', SeguimientoController::class);
     */
    public function porAprendiz(Request $request)
    {
        $validated = $request->validate([
            'idpersona' => 'required|integer|exists:persona,id',
        ]);

        $seguimiento = SeguimientoAprendiz::with(['persona', 'contrato.persona', 'documentos'])
            ->where('idpersona', $validated['idpersona'])
            ->first();

        return response()->json($seguimiento);
    }

    /**
     * Devuelve los seguimientos asignados a un instructor (por idcontrato o idpersona del instructor).
     */
    public function porInstructor(Request $request)
    {
        $idPersona = $request->query('idpersona');
        $idContrato = $request->query('idcontrato');

        $query = SeguimientoAprendiz::with(['persona', 'contrato.persona', 'documentos']);

        if ($idContrato) {
            $query->where('idcontrato', $idContrato);
        } elseif ($idPersona) {
            $query->whereHas('contrato', function ($q) use ($idPersona) {
                $q->where('idpersona', $idPersona);
            });
        } elseif ($request->user() && $request->user()->idpersona) {
            $userPersonaId = $request->user()->idpersona;
            $query->whereHas('contrato', function ($q) use ($userPersonaId) {
                $q->where('idpersona', $userPersonaId);
            });
        }

        $seguimientos = $query->latest('id')->get();

        return response()->json($seguimientos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'idpersona' => 'required|integer|exists:persona,id',
            'idcontrato' => 'required|integer|exists:contrato,id',
            'estado' => ['sometimes', Rule::in(SeguimientoAprendiz::ESTADOS)],
        ]);

        // Evita duplicar seguimientos para el mismo aprendiz/contrato
        $existente = SeguimientoAprendiz::where('idpersona', $validated['idpersona'])
            ->where('idcontrato', $validated['idcontrato'])
            ->first();

        if ($existente) {
            return response()->json($existente->load(['persona', 'contrato', 'documentos']), 200);
        }

        $seguimiento = SeguimientoAprendiz::create($validated);

        // Para enviar un correo a las personas interesadas (Instructor de seguimiento y Aprendiz)
        $instructor = Contract::with('persona')->find($validated['idcontrato']);
        $aprendiz = Person::find($validated['idpersona']);

        if ($instructor?->persona && $aprendiz) {
            $mensajeInstructor = "Estimado(a) {$instructor->persona->nombre1} {$instructor->persona->apellido1},\n\n"
                . "Le informamos que se le ha asignado el seguimiento de la etapa productiva del siguiente aprendiz:\n\n"
                . "• Nombre: {$aprendiz->nombre1} {$aprendiz->apellido1}\n"
                . "• Identificación: {$aprendiz->identificacion}\n"
                . "• Correo: {$aprendiz->email}\n\n"
                . "A partir de este momento, usted será el encargado de realizar el acompañamiento y seguimiento correspondiente. "
                . "Puede ingresar a la plataforma para consultar el detalle del contrato y comenzar el registro de las visitas y documentos requeridos.\n\n"
                . "Si tiene alguna duda sobre el proceso, no dude en contactar al equipo administrativo.\n\n"
                . "Cordialmente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            $mensajeAprendiz = "Estimado(a) {$aprendiz->nombre1} {$aprendiz->apellido1},\n\n"
                . "Le informamos que ha sido asignado(a) al siguiente instructor para el seguimiento de su etapa productiva:\n\n"
                . "• Instructor: {$instructor->persona->nombre1} {$instructor->persona->apellido1}\n"
                . "• Correo: {$instructor->persona->email}\n\n"
                . "Su instructor estará a cargo de acompañarlo(a) durante este proceso. "
                . "Le recomendamos ingresar a la plataforma para revisar los requisitos, cargar los documentos solicitados y mantenerse al tanto de las novedades de su seguimiento.\n\n"
                . "Si tiene alguna inquietud, puede comunicarse con su instructor o con el equipo administrativo.\n\n"
                . "Cordialmente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            SendBasicEmail::dispatch($instructor->persona->email, 'Seguimiento etapa productiva', $mensajeInstructor);
            SendBasicEmail::dispatch($aprendiz->email, 'Seguimiento etapa productiva', $mensajeAprendiz);
        }

        return response()->json($seguimiento->load(['persona', 'contrato', 'documentos']), 201);
    }

    public function show(SeguimientoAprendiz $seguimiento)
    {
        return response()->json($seguimiento->load(['persona', 'contrato', 'documentos']));
    }

    public function update(Request $request, SeguimientoAprendiz $seguimiento)
    {
        $validated = $request->validate([
            'idpersona' => 'sometimes|integer|exists:persona,id',
            'idcontrato' => 'sometimes|integer|exists:contrato,id',
            'estado' => ['sometimes', Rule::in(SeguimientoAprendiz::ESTADOS)],
        ]);

        $seguimiento->update($validated);

        return response()->json($seguimiento->load(['persona', 'contrato.persona', 'documentos']));
    }

    public function destroy(SeguimientoAprendiz $seguimiento)
    {
        // Borra primero los archivos físicos y registros de documentos asociados
        foreach ($seguimiento->documentos as $documento) {
            $this->eliminarArchivoFisico($documento->documentoUrl);
            $documento->delete();
        }

        $seguimiento->delete();

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════════════════════════
    //  DOCUMENTOS DEL SEGUIMIENTO
    // ══════════════════════════════════════════════════════════════

    public function documentosIndex(SeguimientoAprendiz $seguimiento)
    {
        return response()->json($seguimiento->documentos()->latest('id')->get());
    }

    public function documentosStore(Request $request, SeguimientoAprendiz $seguimiento)
    {
        $validated = $request->validate([
            'archivo' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'nombre_documento' => 'required|string|max:255',
        ]);

        $ruta = $request->file('archivo')->store(self::RUTA_DOCUMENTOS, 'public');

        $documento = DocumentoSeguimiento::create([
            'documentoUrl' => $ruta,
            'nombre_documento' => $validated['nombre_documento'],
            'estado' => 'PENDIENTE',
            'idseguimiento' => $seguimiento->id,
        ]);

        // Notificar por correo al instructor asignado
        $seguimiento->load(['persona', 'contrato.persona']);
        $instructorPersona = $seguimiento->contrato?->persona;
        $aprendizPersona = $seguimiento->persona;

        if ($instructorPersona?->email) {
            $nombreInstructor = trim("{$instructorPersona->nombre1} {$instructorPersona->apellido1}");
            $nombreAprendiz = trim("{$aprendizPersona?->nombre1} {$aprendizPersona?->apellido1}");
            $identificacionAprendiz = $aprendizPersona?->identificacion ?? 'N/A';

            $mensajeInstructor = "Estimado(a) {$nombreInstructor},\n\n"
                . "Le informamos que el aprendiz {$nombreAprendiz} (Identificación: {$identificacionAprendiz}) ha subido un nuevo documento en el seguimiento de su etapa productiva:\n\n"
                . "• Documento: {$documento->nombre_documento}\n"
                . "• Estado: PENDIENTE\n\n"
                . "Por favor, ingrese a la plataforma para revisar y evaluar el documento cargado.\n\n"
                . "Cordialmente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            SendBasicEmail::dispatch($instructorPersona->email, 'Nuevo documento de seguimiento cargado', $mensajeInstructor);
        }

        return response()->json($documento, 201);
    }

    public function documentosShow(DocumentoSeguimiento $documento)
    {
        return response()->json($documento->load('seguimiento'));
    }

    public function documentosUpdate(Request $request, DocumentoSeguimiento $documento)
    {
        $validated = $request->validate([
            'archivo' => 'sometimes|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'nombre_documento' => 'sometimes|string|max:255',
        ]);

        $archivoReemplazado = $request->hasFile('archivo');

        if ($archivoReemplazado) {
            $this->eliminarArchivoFisico($documento->documentoUrl);
            $validated['documentoUrl'] = $request->file('archivo')->store(self::RUTA_DOCUMENTOS, 'public');
            $validated['estado'] = 'PENDIENTE'; // vuelve a quedar pendiente si se reemplaza
            $validated['observacion'] = null; // limpia la observacion de rechazo al reemplazar
        }

        $documento->update($validated);

        if ($archivoReemplazado) {
            // Notificar al instructor cuando el aprendiz reemplaza/actualiza el archivo
            $documento->load(['seguimiento.persona', 'seguimiento.contrato.persona']);
            $instructorPersona = $documento->seguimiento?->contrato?->persona;
            $aprendizPersona = $documento->seguimiento?->persona;

            if ($instructorPersona?->email) {
                $nombreInstructor = trim("{$instructorPersona->nombre1} {$instructorPersona->apellido1}");
                $nombreAprendiz = trim("{$aprendizPersona?->nombre1} {$aprendizPersona?->apellido1}");
                $identificacionAprendiz = $aprendizPersona?->identificacion ?? 'N/A';

                $mensajeInstructor = "Estimado(a) {$nombreInstructor},\n\n"
                    . "Le informamos que el aprendiz {$nombreAprendiz} (Identificación: {$identificacionAprendiz}) ha actualizado y re-subido el siguiente documento de seguimiento:\n\n"
                    . "• Documento: {$documento->nombre_documento}\n"
                    . "• Estado: PENDIENTE\n\n"
                    . "Por favor, ingrese a la plataforma para revisar el documento actualizado.\n\n"
                    . "Cordialmente,\n"
                    . "Equipo Administrativo\n"
                    . "Sistema de Gestión Académica";

                SendBasicEmail::dispatch($instructorPersona->email, 'Documento de seguimiento actualizado', $mensajeInstructor);
            }
        }

        return response()->json($documento);
    }

    // Aprobar / rechazar documento
    public function documentosCambiarEstado(Request $request, DocumentoSeguimiento $documento)
    {
        $validated = $request->validate([
            'estado' => ['required', Rule::in(DocumentoSeguimiento::ESTADOS)],
            'observacion' => 'required_if:estado,RECHAZADO|nullable|string',
        ]);

        $documento->update($validated);

        // Notificar por correo al aprendiz sobre el cambio de estado del documento
        $documento->load(['seguimiento.persona']);
        $aprendizPersona = $documento->seguimiento?->persona;

        if ($aprendizPersona?->email) {
            $nombreAprendiz = trim("{$aprendizPersona->nombre1} {$aprendizPersona->apellido1}");
            $estadoStr = $documento->estado;
            $observacionTexto = (!empty($documento->observacion)) ? "\n• Observación: {$documento->observacion}" : '';

            $mensajeAprendiz = "Estimado(a) {$nombreAprendiz},\n\n"
                . "Le informamos que su documento de seguimiento ha cambiado de estado en la plataforma:\n\n"
                . "• Documento: {$documento->nombre_documento}\n"
                . "• Nuevo Estado: {$estadoStr}{$observacionTexto}\n\n"
                . "Puede ingresar a la plataforma para consultar el detalle de sus documentos"
                . ($estadoStr === 'RECHAZADO' ? " y realizar la corrección correspondiente." : ".") . "\n\n"
                . "Cordialmente,\n"
                . "Equipo Administrativo\n"
                . "Sistema de Gestión Académica";

            SendBasicEmail::dispatch($aprendizPersona->email, 'Cambio de estado en documento de seguimiento', $mensajeAprendiz);
        }

        return response()->json($documento);
    }

    public function documentosDestroy(DocumentoSeguimiento $documento)
    {
        $this->eliminarArchivoFisico($documento->documentoUrl);
        $documento->delete();

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════

    private function eliminarArchivoFisico(?string $ruta): void
    {
        if ($ruta && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
