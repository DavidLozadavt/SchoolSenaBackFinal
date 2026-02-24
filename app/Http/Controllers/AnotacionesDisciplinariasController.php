<?php

namespace App\Http\Controllers;

use App\Models\AnotacionesDisciplinarias;
use App\Models\Contract;
use App\Models\Matricula;
use App\Util\KeyUtil;
use Illuminate\Http\Request;



class AnotacionesDisciplinariasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $anotaciones = AnotacionesDisciplinarias::with(['matricula', 'contrato', 'grado'])->get();
        return response()->json($anotaciones);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {
        try {
            $user = KeyUtil::user();
            $idPersona = $user->persona->id;
            $contrato = Contract::where('idpersona', $idPersona)->first();

            if (!$contrato) {
                return response()->json(['message' => 'No se encontró un contrato para la persona autenticada'], 403);
            }
            $idContrato = $contrato->id;
            $anotaciones = new AnotacionesDisciplinarias();
            $anotaciones->observacion = $request->input('observacion');
            $anotaciones->fecha = $request->input('fecha');
            $anotaciones->idEstudiante = $request->input('idEstudiante');
            $anotaciones->gradoAnotacion = $request->input('gradoAnotacion');
            $anotaciones->idDocente = $idContrato;
            $anotaciones->saveFileanotaciones($request);
            $anotaciones->save();

            $matricula = Matricula::find($request->input('idEstudiante'));
            if ($matricula) {
                $anotaciones->notificarAnotacionEstudiante([$matricula->idPersona]);
            }

            return response()->json($anotaciones, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la anotación', 'error' => $e->getMessage()], 500);
        }
    }

    public function anotacionesMatricula(Request $request, $idMatricula)
    {
        try {
            $user = KeyUtil::user();
            $idPersona = $user->persona->id;
            $contrato = Contract::where('idpersona', $idPersona)->first();

            if (!$contrato) {
                return response()->json(['message' => 'No se encontró un contrato para la persona autenticada'], 403);
            }

            $matricula = Matricula::find($idMatricula);
            if (!$matricula) {
                return response()->json(['message' => 'No se encontró la matrícula'], 404);
            }
            $idEstudiantePersona = $matricula->idPersona;

            $idContrato = $contrato->id;
            $anotaciones = new AnotacionesDisciplinarias();
            $anotaciones->observacion = $request->input('observacion');
            $anotaciones->fecha = $request->input('fecha');
            $anotaciones->idEstudiante = $idMatricula;
            $anotaciones->gradoAnotacion = $request->input('gradoAnotacion');
            $anotaciones->idDocente = $idContrato;
            $anotaciones->saveFileanotaciones($request);
            $anotaciones->save();
            
            $anotaciones->notificarAnotacionEstudiante([$idEstudiantePersona]);

            return response()->json($anotaciones, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la anotación', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AnotacionesDisciplinarias  $anotacionesDisciplinarias
     * @return \Illuminate\Http\Response
     */
    public function show(AnotacionesDisciplinarias $anotacionesDisciplinarias)
    {
        $anotacion = $anotacionesDisciplinarias->load(['matricula', 'contrato', 'grado']);
        return response()->json($anotacion);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AnotacionesDisciplinarias  $anotacionesDisciplinarias
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AnotacionesDisciplinarias $anotacionesDisciplinarias)
    {
        $validatedData = $request->validate([
            'observacion' => 'nullable|string|max:255',
            'urlDocumento' => 'nullable|string|max:255',
            'fecha' => 'required|date',
            'idMatricula' => 'required|integer|exists:matricula,id',
            'idContrato' => 'required|integer|exists:contrato,id',
            'idGrado' => 'required|integer|exists:grado,id',
        ]);

        $anotacionesDisciplinarias->update($validatedData);

        return response()->json(['message' => 'Anotación actualizada exitosamente', 'data' => $anotacionesDisciplinarias]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AnotacionesDisciplinarias  $anotacionesDisciplinarias
     * @return \Illuminate\Http\Response
     */
    public function destroy(AnotacionesDisciplinarias $anotacionesDisciplinarias)
    {
        $anotacionesDisciplinarias->delete();

        return response()->json(['message' => 'Anotación eliminada exitosamente']);
    }

    public function obtenerAnotacionesPorMatricula($idMatricula)
    {
        $matricula = Matricula::with(['anotacionesDisciplinarias.contrato.persona'])
            ->findOrFail($idMatricula);

        return response()->json($matricula->anotacionesDisciplinarias);
    }

    public function getAnotacionesByMatriculaAcademica($idMatricula)
    {
        try {
            $anotaciones = AnotacionesDisciplinarias::with(['contrato.persona'])
                ->where('idEstudiante', $idMatricula)
                ->get();

            return response()->json($anotaciones, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar anotaciones',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

}
