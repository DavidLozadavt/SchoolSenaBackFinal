<?php

namespace App\Http\Controllers\ambiente_virtual;

use App\Http\Controllers\Controller;
use App\Models\PlaneacionPedagogica;
use App\Models\PlaneacionPedagogicaClase;
use App\Util\KeyUtil;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PlaneacionPedagogicaController extends Controller
{
    private function contratoId(): ?int
    {
        try {
            $c = KeyUtil::lastContractActive();
            return $c?->id ? (int) $c->id : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function findOwned(int $id): ?PlaneacionPedagogica
    {
        $contratoId = $this->contratoId();
        if (!$contratoId) {
            return null;
        }

        return PlaneacionPedagogica::with('clases')
            ->where('id', $id)
            ->where('idContrato', $contratoId)
            ->first();
    }

    public function index(): JsonResponse
    {
        $contratoId = $this->contratoId();
        if (!$contratoId) {
            return response()->json(['message' => 'Sin contrato activo', 'data' => []], 200);
        }

        $this->marcarVencidasSinMotivo($contratoId);

        $rows = PlaneacionPedagogica::with(['clases:id,idPlaneacion,idHorarioMateria,asignatura,tallerInicio,tallerFin'])
            ->withCount('clases')
            ->where('idContrato', $contratoId)
            ->orderByDesc('semanaInicio')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $rows], 200);
    }

    public function show(int $id): JsonResponse
    {
        $plan = $this->findOwned($id);
        if (!$plan) {
            return response()->json(['message' => 'Planeación no encontrada'], 404);
        }

        $contratoId = $this->contratoId();
        if ($contratoId) {
            $this->marcarVencidasSinMotivo($contratoId, $id);
            $plan = $this->findOwned($id) ?? $plan;
        }

        return response()->json(['data' => $plan], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $contratoId = $this->contratoId();
        if (!$contratoId) {
            return response()->json(['message' => 'Sin contrato activo'], 400);
        }

        $data = $request->validate([
            'semanaInicio' => 'required|date',
            'semanaFin' => 'required|date|after_or_equal:semanaInicio',
            'nivel' => 'required|in:PRIMARIA,BACHILLER',
            'idFicha' => 'nullable|integer',
            'grado' => 'nullable|string|max:80',
            'periodo' => 'nullable|string|max:120',
            'institucion' => 'nullable|string|max:255',
            'docenteNombre' => 'nullable|string|max:255',
            'proposito' => 'nullable|string',
            'temaIntegrador' => 'nullable|string|max:500',
            'metodologia' => 'nullable|string|max:500',
            'clases' => 'nullable|array',
            'clases.*.idHorarioMateria' => 'nullable|integer',
            'clases.*.idDia' => 'nullable|integer',
            'clases.*.diaNombre' => 'nullable|string|max:40',
            'clases.*.asignatura' => 'nullable|string|max:255',
            'clases.*.horaInicial' => 'nullable|string|max:20',
            'clases.*.horaFinal' => 'nullable|string|max:20',
            'clases.*.tema' => 'nullable|string|max:500',
            'clases.*.aprendizajeEsperado' => 'nullable|string',
            'clases.*.preguntaProblematizadora' => 'nullable|string',
            'clases.*.saberesPrevios' => 'nullable|string',
            'clases.*.estandar' => 'nullable|string',
            'clases.*.dba' => 'nullable|string',
            'clases.*.competencia' => 'nullable|string',
            'clases.*.evidencia' => 'nullable|string',
            'clases.*.criterios' => 'nullable|string',
            'clases.*.instrumento' => 'nullable|string|max:255',
            'clases.*.recursos' => 'nullable|string',
            'clases.*.refuerzo' => 'nullable|string',
            'clases.*.profundizacion' => 'nullable|string',
            'clases.*.actividadPractica' => 'nullable|string',
            'clases.*.secuencia' => 'nullable|array',
            'clases.*.tallerTitulo' => 'nullable|string|max:255',
            'clases.*.tallerContenido' => 'nullable|string',
            'clases.*.tallerEstrategia' => 'nullable|string',
            'clases.*.tallerEntregables' => 'nullable|string',
            'clases.*.tallerInicio' => 'nullable|date',
            'clases.*.tallerFin' => 'nullable|date|after_or_equal:clases.*.tallerInicio',
            'clases.*.idActividad' => 'nullable|integer',
            'clases.*.idMateria' => 'nullable|integer',
            'clases.*.orden' => 'nullable|integer',
        ]);

        $metodologiaDefault = $data['nivel'] === 'PRIMARIA'
            ? 'Aprendizaje activo, juego, exploración y material concreto'
            : 'Aprendizaje activo, resolución de problemas, debate y trabajo colaborativo';

        DB::beginTransaction();
        try {
            $contrato = DB::table('contrato as c')
                ->leftJoin('persona as p', 'c.idpersona', '=', 'p.id')
                ->leftJoin('empresa as co', 'c.idCompany', '=', 'co.id')
                ->where('c.id', $contratoId)
                ->select(
                    'c.idCompany',
                    DB::raw("TRIM(CONCAT(COALESCE(p.nombre1,''),' ',COALESCE(p.apellido1,''))) as docente"),
                    'co.razonSocial as institucion'
                )
                ->first();

            $plan = PlaneacionPedagogica::create([
                'idContrato' => $contratoId,
                'idFicha' => $data['idFicha'] ?? null,
                'idCompany' => $contrato->idCompany ?? null,
                'semanaInicio' => $data['semanaInicio'],
                'semanaFin' => $data['semanaFin'],
                'nivel' => $data['nivel'],
                'grado' => $data['grado'] ?? null,
                'periodo' => $data['periodo'] ?? null,
                'institucion' => $data['institucion'] ?? ($contrato->institucion ?? 'Institución educativa'),
                'docenteNombre' => $data['docenteNombre'] ?? ($contrato->docente ?? 'Docente'),
                'proposito' => $data['proposito'] ?? null,
                'temaIntegrador' => $data['temaIntegrador'] ?? null,
                'metodologia' => $data['metodologia'] ?? $metodologiaDefault,
                // Al crear ya cuenta como en curso para el docente
                'estado' => 'EN_EJECUCION',
            ]);

            $this->syncClases($plan, $data['clases'] ?? []);

            DB::commit();
            $plan->load('clases');

            return response()->json([
                'message' => 'Planeación creada',
                'data' => $plan,
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error creando planeación pedagógica: ' . $e->getMessage());
            return response()->json([
                'message' => 'No se pudo crear la planeación. Intenta de nuevo.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = $this->findOwned($id);
        if (!$plan) {
            return response()->json(['message' => 'Planeación no encontrada'], 404);
        }

        if (in_array($plan->estado, ['CERRADA'], true)) {
            return response()->json(['message' => 'La planeación está finalizada. Reábrela como en curso si necesitas editarla.'], 422);
        }

        $data = $request->validate([
            'semanaInicio' => 'sometimes|date',
            'semanaFin' => 'sometimes|date',
            'nivel' => 'sometimes|in:PRIMARIA,BACHILLER',
            'idFicha' => 'nullable|integer',
            'grado' => 'nullable|string|max:80',
            'periodo' => 'nullable|string|max:120',
            'institucion' => 'nullable|string|max:255',
            'docenteNombre' => 'nullable|string|max:255',
            'proposito' => 'nullable|string',
            'temaIntegrador' => 'nullable|string|max:500',
            'metodologia' => 'nullable|string|max:500',
            'reflexionDocente' => 'nullable|string',
            'motivoIncompleta' => 'nullable|string',
            'estado' => 'sometimes|in:BORRADOR,ENVIADA,APROBADA,EN_EJECUCION,CERRADA,INCOMPLETA',
            'clases' => 'nullable|array',
            'clases.*.id' => 'nullable|integer',
            'clases.*.idHorarioMateria' => 'nullable|integer',
            'clases.*.idDia' => 'nullable|integer',
            'clases.*.diaNombre' => 'nullable|string|max:40',
            'clases.*.asignatura' => 'nullable|string|max:255',
            'clases.*.horaInicial' => 'nullable|string|max:20',
            'clases.*.horaFinal' => 'nullable|string|max:20',
            'clases.*.tema' => 'nullable|string|max:500',
            'clases.*.aprendizajeEsperado' => 'nullable|string',
            'clases.*.preguntaProblematizadora' => 'nullable|string',
            'clases.*.saberesPrevios' => 'nullable|string',
            'clases.*.estandar' => 'nullable|string',
            'clases.*.dba' => 'nullable|string',
            'clases.*.competencia' => 'nullable|string',
            'clases.*.evidencia' => 'nullable|string',
            'clases.*.criterios' => 'nullable|string',
            'clases.*.instrumento' => 'nullable|string|max:255',
            'clases.*.recursos' => 'nullable|string',
            'clases.*.refuerzo' => 'nullable|string',
            'clases.*.profundizacion' => 'nullable|string',
            'clases.*.actividadPractica' => 'nullable|string',
            'clases.*.secuencia' => 'nullable|array',
            'clases.*.tallerTitulo' => 'nullable|string|max:255',
            'clases.*.tallerContenido' => 'nullable|string',
            'clases.*.tallerEstrategia' => 'nullable|string',
            'clases.*.tallerEntregables' => 'nullable|string',
            'clases.*.tallerInicio' => 'nullable|date',
            'clases.*.tallerFin' => 'nullable|date|after_or_equal:clases.*.tallerInicio',
            'clases.*.idActividad' => 'nullable|integer',
            'clases.*.idMateria' => 'nullable|integer',
            'clases.*.ejecutada' => 'nullable|boolean',
            'clases.*.orden' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            $plan->fill(collect($data)->except('clases')->all());
            $plan->save();

            if (array_key_exists('clases', $data)) {
                $this->syncClases($plan, $data['clases'] ?? [], true);
            }

            DB::commit();
            $plan->load('clases');

            return response()->json([
                'message' => 'Planeación actualizada',
                'data' => $plan,
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error actualizando planeación pedagógica: ' . $e->getMessage());
            return response()->json(['message' => 'No se pudo actualizar'], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = $this->findOwned($id);
        if (!$plan) {
            return response()->json(['message' => 'Planeación no encontrada'], 404);
        }

        if (!in_array($plan->estado, ['BORRADOR', 'EN_EJECUCION'], true)) {
            return response()->json(['message' => 'No se puede eliminar una planeación enviada, finalizada o incompleta'], 422);
        }

        $plan->delete();
        return response()->json(['message' => 'Planeación eliminada'], 200);
    }

    /**
     * Cambia el estado visible: en curso / finalizada / incompleta.
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $plan = $this->findOwned($id);
        if (!$plan) {
            return response()->json(['message' => 'Planeación no encontrada'], 404);
        }

        $data = $request->validate([
            'estado' => 'required|in:EN_EJECUCION,CERRADA,INCOMPLETA',
            'motivoIncompleta' => 'nullable|string|max:2000',
        ]);

        if ($data['estado'] === 'INCOMPLETA' && empty(trim((string) ($data['motivoIncompleta'] ?? '')))) {
            $data['motivoIncompleta'] = 'Sin motivo';
        }

        $plan->estado = $data['estado'];
        if ($data['estado'] === 'INCOMPLETA') {
            $plan->motivoIncompleta = trim((string) $data['motivoIncompleta']);
        } elseif ($data['estado'] === 'EN_EJECUCION') {
            $plan->motivoIncompleta = null;
        }

        $plan->save();
        $plan->load('clases');

        return response()->json([
            'message' => 'Estado actualizado',
            'data' => $plan,
        ], 200);
    }

    private function buildPlaneacionPdf(PlaneacionPedagogica $plan)
    {
        $view = $plan->nivel === 'BACHILLER'
            ? 'pdf.planeacion-pedagogica-bachiller'
            : 'pdf.planeacion-pedagogica-primaria';

        return Pdf::loadView($view, ['plan' => $plan])
            ->setPaper('letter', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('isRemoteEnabled', false);
    }

    public function pdf(int $id)
    {
        $plan = $this->findOwned($id);
        if (!$plan) {
            return response()->json(['message' => 'Planeación no encontrada'], 404);
        }

        $pdf = $this->buildPlaneacionPdf($plan);
        $filename = 'planeacion_' . strtolower($plan->nivel) . '_semana_' . $plan->semanaInicio->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $plan = $this->findOwned($id);
        if (!$plan) {
            return response()->json(['message' => 'Planeación no encontrada'], 404);
        }

        $data = $request->validate([
            'coordinadorEmail' => 'required|email',
            'reflexionDocente' => 'nullable|string',
        ]);

        if ($data['reflexionDocente'] ?? null) {
            $plan->reflexionDocente = $data['reflexionDocente'];
        }

        try {
            $pdf = $this->buildPlaneacionPdf($plan);
            $relative = 'planeaciones/' . $plan->id . '_' . time() . '.pdf';
            Storage::disk('local')->put($relative, $pdf->output());

            $plan->pdfPath = $relative;
            $plan->coordinadorEmail = $data['coordinadorEmail'];
            $plan->estado = 'ENVIADA';
            $plan->enviadoAt = now();
            $plan->save();

            $fromAddress = config('mail.from.address');
            $fromName = config('mail.from.name', 'School SENA');

            if (!empty($fromAddress)) {
                try {
                    $pdfBinary = $pdf->output();
                    Mail::send('mails.planeacion-enviada', [
                        'plan' => $plan,
                        'docente' => $plan->docenteNombre,
                    ], function ($message) use ($plan, $fromAddress, $fromName, $pdfBinary) {
                        $message->from($fromAddress, $fromName)
                            ->to($plan->coordinadorEmail)
                            ->subject('Planeación pedagógica — ' . $plan->docenteNombre . ' — semana ' . $plan->semanaInicio->format('d/m/Y'))
                            ->attachData($pdfBinary, 'planeacion_pedagogica.pdf', [
                                'mime' => 'application/pdf',
                            ]);
                    });
                } catch (Throwable $mailEx) {
                    Log::error('Error enviando planeación por correo: ' . $mailEx->getMessage());
                }
            }

            return response()->json([
                'message' => 'Planeación enviada al coordinador',
                'data' => $plan->fresh('clases'),
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error al enviar planeación: ' . $e->getMessage());
            return response()->json(['message' => 'No se pudo generar o enviar el PDF'], 500);
        }
    }

    public function marcarEjecutada(Request $request, int $claseId): JsonResponse
    {
        $contratoId = $this->contratoId();
        $clase = PlaneacionPedagogicaClase::where('id', $claseId)
            ->whereHas('planeacion', fn ($q) => $q->where('idContrato', $contratoId))
            ->first();

        if (!$clase) {
            return response()->json(['message' => 'Clase no encontrada'], 404);
        }

        $data = $request->validate([
            'ejecutada' => 'required|boolean',
        ]);

        $clase->ejecutada = (bool) $data['ejecutada'];
        $clase->save();

        $plan = $clase->planeacion;
        $total = $plan->clases()->count();
        $hechas = $plan->clases()->where('ejecutada', true)->count();
        if ($hechas > 0 && $plan->estado === 'APROBADA') {
            $plan->estado = 'EN_EJECUCION';
            $plan->save();
        } elseif ($hechas > 0 && $plan->estado === 'ENVIADA') {
            $plan->estado = 'EN_EJECUCION';
            $plan->save();
        } elseif ($total > 0 && $hechas === $total) {
            $plan->estado = 'EN_EJECUCION';
            $plan->save();
        }

        return response()->json([
            'message' => 'Estado de ejecución actualizado',
            'data' => $clase->fresh(),
        ], 200);
    }

    /**
     * Si ya pasó el viernes de la semana y la planeación sigue en curso / enviada,
     * se marca incompleta sin motivo (editable después).
     */
    private function marcarVencidasSinMotivo(int $contratoId, ?int $soloId = null): void
    {
        $q = PlaneacionPedagogica::where('idContrato', $contratoId)
            ->whereIn('estado', ['BORRADOR', 'ENVIADA', 'APROBADA', 'EN_EJECUCION'])
            ->whereDate('semanaFin', '<', now()->toDateString());

        if ($soloId) {
            $q->where('id', $soloId);
        }

        foreach ($q->get() as $plan) {
            $plan->estado = 'INCOMPLETA';
            if (trim((string) ($plan->motivoIncompleta ?? '')) === '') {
                $plan->motivoIncompleta = 'Sin motivo';
            }
            $plan->save();
        }
    }

    private function syncClases(PlaneacionPedagogica $plan, array $clases, bool $replace = false): void
    {
        if ($replace) {
            $keepIds = [];
            foreach ($clases as $i => $c) {
                $payload = $this->mapClasePayload($c, $i);
                if (!empty($c['id'])) {
                    $row = PlaneacionPedagogicaClase::where('id', $c['id'])
                        ->where('idPlaneacion', $plan->id)
                        ->first();
                    if ($row) {
                        $row->update($payload);
                        $keepIds[] = $row->id;
                        continue;
                    }
                }
                $created = $plan->clases()->create($payload);
                $keepIds[] = $created->id;
            }
            PlaneacionPedagogicaClase::where('idPlaneacion', $plan->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
            return;
        }

        foreach ($clases as $i => $c) {
            $plan->clases()->create($this->mapClasePayload($c, $i));
        }
    }

    private function mapClasePayload(array $c, int $orden): array
    {
        return [
            'idHorarioMateria' => $c['idHorarioMateria'] ?? null,
            'idDia' => $c['idDia'] ?? null,
            'diaNombre' => $c['diaNombre'] ?? null,
            'asignatura' => $c['asignatura'] ?? null,
            'horaInicial' => $c['horaInicial'] ?? null,
            'horaFinal' => $c['horaFinal'] ?? null,
            'tema' => $c['tema'] ?? null,
            'aprendizajeEsperado' => $c['aprendizajeEsperado'] ?? null,
            'preguntaProblematizadora' => $c['preguntaProblematizadora'] ?? null,
            'saberesPrevios' => $c['saberesPrevios'] ?? null,
            'estandar' => $c['estandar'] ?? null,
            'dba' => $c['dba'] ?? null,
            'competencia' => $c['competencia'] ?? null,
            'evidencia' => $c['evidencia'] ?? null,
            'criterios' => $c['criterios'] ?? null,
            'instrumento' => $c['instrumento'] ?? ($c['instrumento'] ?? null),
            'recursos' => $c['recursos'] ?? null,
            'refuerzo' => $c['refuerzo'] ?? null,
            'profundizacion' => $c['profundizacion'] ?? null,
            'actividadPractica' => $c['actividadPractica'] ?? null,
            'secuencia' => $c['secuencia'] ?? $this->secuenciaDefault(),
            'tallerTitulo' => $c['tallerTitulo'] ?? null,
            'tallerContenido' => $c['tallerContenido'] ?? null,
            'tallerEstrategia' => $c['tallerEstrategia'] ?? null,
            'tallerEntregables' => $c['tallerEntregables'] ?? null,
            'tallerInicio' => $c['tallerInicio'] ?? null,
            'tallerFin' => $c['tallerFin'] ?? null,
            'idActividad' => $c['idActividad'] ?? null,
            'idMateria' => $c['idMateria'] ?? null,
            'ejecutada' => (bool) ($c['ejecutada'] ?? false),
            'orden' => $c['orden'] ?? $orden,
        ];
    }

    private function secuenciaDefault(): array
    {
        return [
            ['momento' => 'Inicio', 'actividad' => '', 'tiempo' => '15 min'],
            ['momento' => 'Desarrollo', 'actividad' => '', 'tiempo' => '60 min'],
            ['momento' => 'Cierre', 'actividad' => '', 'tiempo' => '15 min'],
        ];
    }
}
