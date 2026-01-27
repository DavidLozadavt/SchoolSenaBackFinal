<?php

namespace App\Http\Controllers;

use App\Http\Resources\AperturarProgramaResource;
use App\Models\AperturarPrograma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AperturarProgramaController extends Controller
{
    public function index(): JsonResponse
    {
        $data = AperturarPrograma::with(['periodo:id,nombrePeriodo', 'programa:id,nombrePrograma,codigoPrograma', 'sede:id,nombre'])->get();

        return response()->json(AperturarProgramaResource::collection($data));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'observacion' => 'required|string|max:1000',

            'idPeriodo' => 'required|exists:periodo,id',
            'idPrograma' => 'required|exists:programa,id',
            'estado' => 'required|in:ACTIVO,INACTIVO,OCULTO,PENDIENTE,RECHAZADO,APROBADO,CANCELADO,REPROBADO,CERRADO,ACEPTADO,LEIDO,EN ESPERA,INSCRIPCION,MATRICULADO,ABIERTO,EN CURSO,POR ACTUALIZAR,CURSANDO,ENTREVISTA,SIN ENTREVISTA,JUSTIFICADO',
            'idSede' => 'required|exists:sedes,id',
            'fechaInicialClases' => 'required|date',
            'fechaFinalClases' => 'required|date|after_or_equal:fechaInicialClases',
            'fechaInicialInscripciones' => 'required|date',
            'fechaFinalInscripciones' => 'required|date|after_or_equal:fechaInicialInscripciones',
            'fechaInicialMatriculas' => 'required|date',
            'fechaFinalMatriculas' => 'required|date|after_or_equal:fechaInicialMatriculas',
            'fechaInicialPlanMejoramiento' => 'required|date',
            'fechaFinalPlanMejoramiento' => 'required|date|after_or_equal:fechaInicialPlanMejoramiento',
            'tipoCalificacion' => 'required|in:NUMERICO,DESEMPEÑO',
        ]);

        $apertura = AperturarPrograma::create($validated);

        return response()->json([
            'message' => 'Programa aperturado correctamente',
            'data' => $apertura
        ], 201);
    }


    public function show($id): JsonResponse
    {
        $apertura = AperturarPrograma::with(['periodo:id,nombrePeriodo', 'programa:id,nombrePrograma,codigoPrograma', 'sede:id,nombre'])->findOrFail($id);

        return response()->json(new AperturarProgramaResource($apertura));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $apertura = AperturarPrograma::findOrFail($id);

        $validated = $request->validate([
            'observacion' => 'nullable|string|max:1000',
            'idPeriodo' => 'nullable|exists:periodo,id',
            'idPrograma' => 'nullable|exists:programa,id',
            'estado' => 'nullable|in:ACTIVO,INACTIVO,OCULTO,PENDIENTE,RECHAZADO,APROBADO,CANCELADO,REPROBADO,CERRADO,ACEPTADO,LEIDO,EN ESPERA,INSCRIPCION,MATRICULADO,ABIERTO,EN CURSO,POR ACTUALIZAR,CURSANDO,ENTREVISTA,SIN ENTREVISTA,JUSTIFICADO',
            'idSede' => 'nullable|exists:sedes,id',
            'fechaInicialClases' => 'nullable|date',
            'fechaFinalClases' => 'nullable|date|after_or_equal:fechaInicialClases',
            'fechaInicialInscripciones' => 'nullable|date',
            'fechaFinalInscripciones' => 'nullable|date|after_or_equal:fechaInicialInscripciones',
            'fechaInicialMatriculas' => 'nullable|date',
            'fechaFinalMatriculas' => 'nullable|date|after_or_equal:fechaInicialMatriculas',
            'fechaInicialPlanMejoramiento' => 'nullable|date',
            'fechaFinalPlanMejoramiento' => 'nullable|date|after_or_equal:fechaInicialPlanMejoramiento',
            'tipoCalificacion' => 'nullable|in:NUMERICO,DESEMPEÑO',
        ]);

        $apertura->update($validated);

        return response()->json([
            'message' => 'Registro actualizado correctamente',
            'data' => $apertura
        ]);
    }
}
