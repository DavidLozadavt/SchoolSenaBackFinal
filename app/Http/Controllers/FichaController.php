<?php

namespace App\Http\Controllers;

use App\Models\Ficha;
use App\Models\AperturarPrograma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class FichaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Apertura programa
            'observacion' => 'nullable|string|max:1000',
            'idPeriodo' => 'required|exists:periodo,id',
            'idPrograma' => 'required|exists:programa,id',
            'estado' => 'required|string',
            'idSede' => 'required|exists:sedes,id',
            'tipoCalificacion' => 'nullable|in:NUMERICO,DESEMPEÑO',

            // apertura Fechas:
            'fechaInicialClases' => 'required|date',
            'fechaFinalClases' => 'required|date|after_or_equal:fechaInicialClases',
            'fechaInicialPlanMejoramiento' => 'required|date',
            'fechaFinalPlanMejoramiento' => 'required|date|after_or_equal:fechaInicialPlanMejoramiento',
            'fechaInicialInscripciones' => 'required|date',
            'fechaFinalInscripciones' => 'required|date|after_or_equal:fechaInicialInscripciones',
            'fechaInicialMatriculas' => 'required|date',
            'fechaFinalMatriculas' => 'required|date|after_or_equal:fechaInicialMatriculas',

            // Ficha
            'idJornada' => 'required|exists:jornadas,id',
            'idRegional' => 'required|exists:empresa,id',
            'codigo' => 'required|string|unique:ficha,codigo',
        ]);

        DB::beginTransaction();

        try {
            $apertura = AperturarPrograma::create([
                'observacion' => $validated['observacion'] ?? null,
                'idPeriodo' => $validated['idPeriodo'],
                'idPrograma' => $validated['idPrograma'],
                'estado' => $validated['estado'],
                'idSede' => $validated['idSede'],
                'tipoCalificacion' => $validated['tipoCalificacion'] ?? 'NUMERICO',

                'fechaInicialClases' => $validated['fechaInicialClases'],
                'fechaFinalClases' => $validated['fechaFinalClases'],
                'fechaInicialPlanMejoramiento' => $validated['fechaInicialPlanMejoramiento'],
                'fechaFinalPlanMejoramiento' => $validated['fechaFinalPlanMejoramiento'],
                'fechaInicialInscripciones' => $validated['fechaInicialInscripciones'],
                'fechaFinalInscripciones' => $validated['fechaFinalInscripciones'],
                'fechaInicialMatriculas' => $validated['fechaInicialMatriculas'],
                'fechaFinalMatriculas' => $validated['fechaFinalMatriculas'],
            ]);

            $ficha = Ficha::create([
                'idJornada' => $validated['idJornada'],
                'idAsignacion' => $apertura->id,
                'codigo' => $validated['codigo'],
                'idSede' => $validated['idSede'],
                'idRegional' => $validated['idRegional'],
                'porcentajeEjecucion' => 100,
            ]);


            DB::commit();

            return response()->json([
                'message' => 'Ficha creada correctamente',
                'data' => [
                    'ficha' => $ficha,
                    'apertura' => $apertura
                ]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear la ficha',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function fichasPorRegional(
        Request $request,
        int $idRegional
    ): JsonResponse {

        $estadosPermitidos = [
            'ACTIVO',
            'INACTIVO',
            'OCULTO',
            'PENDIENTE',
            'RECHAZADO',
            'APROBADO',
            'CANCELADO',
            'REPROBADO',
            'CERRADO',
            'ACEPTADO',
            'LEIDO',
            'EN ESPERA',
            'INSCRIPCION',
            'MATRICULADO',
            'ABIERTO',
            'EN CURSO',
            'POR ACTUALIZAR',
            'CURSANDO',
            'ENTREVISTA',
            'SIN ENTREVISTA',
            'JUSTIFICADO',
        ];

        $estado = $request->query('estado', 'ACTIVO'); // default

        if (!in_array($estado, $estadosPermitidos, true)) {
            return response()->json([
                'message' => 'Estado no permitido'
            ], 422);
        }

        $fichas = Ficha::query()
            ->where('idRegional', $idRegional)
            ->whereHas('aperturaPrograma', function ($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->with([
                'regional:id,razonSocial',
                'sede:id,nombre',
                'aperturaPrograma:id,estado,idPrograma'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'estado' => $estado,
            'idRegional' => $idRegional,
            'total' => $fichas->count(),
            'data' => $fichas
        ]);
    }
}
