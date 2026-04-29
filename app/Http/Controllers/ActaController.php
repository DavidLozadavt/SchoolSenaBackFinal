<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $actas = Acta::with(['ciudad', 'ficha', 'contrato.persona', 'novedades', 'agenda', 'objetivos'])->get();
            return response()->json($actas);
        } catch (\Exception $e) {
            Log::error('Error al listar actas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener las actas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha' => 'required|date',
            'horaInicio' => 'required',
            'horaFin' => 'required',
            'tipoActa' => 'required|string|max:255',
            'observacion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'direccion' => 'nullable|string',
            'idCiudad' => 'required|exists:ciudad,id',
            'idFicha' => 'required|exists:ficha,id',
            'idContrato' => 'required|exists:contrato,id',
            // Relaciones
            'agenda' => 'nullable|array',
            'agenda.*.punto' => 'required|string',
            'objetivos' => 'nullable|array',
            'objetivos.*.objetivo' => 'required|string',
            'novedades' => 'nullable|array',
            'novedades.*.idmatriculaAcademica' => 'required|exists:matriculaAcademica,id',
            'novedades.*.observacion' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $acta = Acta::create($validated);

            if (!empty($validated['agenda'])) {
                foreach ($validated['agenda'] as $item) {
                    $acta->agenda()->create($item);
                }
            }

            if (!empty($validated['objetivos'])) {
                foreach ($validated['objetivos'] as $item) {
                    $acta->objetivos()->create($item);
                }
            }

            if (!empty($validated['novedades'])) {
                foreach ($validated['novedades'] as $item) {
                    $acta->novedades()->create($item);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Acta creada correctamente con todos sus detalles',
                'data' => $acta->load(['agenda', 'objetivos', 'novedades'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear acta con detalles: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear el acta y sus detalles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $acta = Acta::with(['ciudad', 'ficha', 'contrato.persona', 'novedades.matriculaAcademica.matricula.persona', 'agenda', 'objetivos'])->findOrFail($id);
            return response()->json($acta);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Acta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al mostrar acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener el acta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'fecha' => 'sometimes|required|date',
            'horaInicio' => 'sometimes|required',
            'horaFin' => 'sometimes|required',
            'tipoActa' => 'sometimes|required|string|max:255',
            'observacion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'direccion' => 'nullable|string',
            'idCiudad' => 'sometimes|required|exists:ciudad,id',
            'idFicha' => 'sometimes|required|exists:ficha,id',
            'idContrato' => 'sometimes|required|exists:contrato,id',
            // Relaciones
            'agenda' => 'nullable|array',
            'agenda.*.punto' => 'required|string',
            'objetivos' => 'nullable|array',
            'objetivos.*.objetivo' => 'required|string',
            'novedades' => 'nullable|array',
            'novedades.*.idmatriculaAcademica' => 'required|exists:matriculaAcademica,id',
            'novedades.*.observacion' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $acta = Acta::findOrFail($id);
            $acta->update($validated);

            // Sincronizar Agenda
            if (isset($validated['agenda'])) {
                $acta->agenda()->delete();
                foreach ($validated['agenda'] as $item) {
                    $acta->agenda()->create($item);
                }
            }

            // Sincronizar Objetivos
            if (isset($validated['objetivos'])) {
                $acta->objetivos()->delete();
                foreach ($validated['objetivos'] as $item) {
                    $acta->objetivos()->create($item);
                }
            }

            // Sincronizar Novedades
            if (isset($validated['novedades'])) {
                $acta->novedades()->delete();
                foreach ($validated['novedades'] as $item) {
                    $acta->novedades()->create($item);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Acta actualizada correctamente',
                'data' => $acta->load(['agenda', 'objetivos', 'novedades'])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Acta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el acta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $acta = Acta::findOrFail($id);
            $acta->delete();
            return response()->json([
                'message' => 'Acta eliminada correctamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Acta no encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar acta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar el acta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get actas by idContrato.
     *
     * @param int $idContrato
     * @return JsonResponse
     */
    public function getByContrato($idContrato): JsonResponse
    {
        try {
            $actas = Acta::where('idContrato', $idContrato)
                ->with(['ciudad', 'ficha', 'contrato.persona', 'novedades', 'agenda', 'objetivos'])
                ->get();

            return response()->json($actas);
        } catch (\Exception $e) {
            Log::error('Error al filtrar actas por contrato: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al filtrar actas por contrato',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get apprentices by idFicha for novedades.
     *
     * @param int $idFicha
     * @return JsonResponse
     */
    public function getAprendicesByFicha($idFicha): JsonResponse
    {
        try {
            $aprendices = \App\Models\MatriculaAcademica::where('idFicha', $idFicha)
                ->with(['matricula.person'])
                ->get()
                ->map(function ($ma) {
                    return [
                        'id' => $ma->id,
                        'nombre' => $ma->matricula->person->nombre1 . ' ' . $ma->matricula->person->apellido1,
                        'identificacion' => $ma->matricula->person->identificacion,
                    ];
                });

            return response()->json($aprendices);
        } catch (\Exception $e) {
            Log::error('Error al obtener aprendices por ficha: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener los aprendices',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getActaInstructor($id)
    {
        try {
            $acta = Acta::where('id', $id)
                ->with(['ciudad', 'ficha', 'contrato.persona', 'novedades.matriculaAcademica.matricula.persona', 'agenda', 'objetivos'])
                ->first();


            $pdf = Pdf::loadView('pdf.actasInstructores', compact('acta'))->setPaper('letter')
                ->setOption('isPhpEnabled', true)
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isFontSubsettingEnabled', true);
            return $pdf->stream('actasInstructor.pdf');
        } catch (\Exception $e) {
            Log::error('Error al obtener actas por contrato: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al filtrar actas por contrato',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
