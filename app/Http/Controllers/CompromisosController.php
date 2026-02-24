<?php

namespace App\Http\Controllers;

use App\Models\AnotacionesDisciplinarias;
use App\Models\Compromiso;
use App\Models\Contract;
use App\Models\Matricula;
use App\Models\Persona;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class CompromisosController extends Controller
{
    public function index()
    {
        $compromisos = Compromiso::all();
        return response()->json($compromisos);
    }

    public function create()
    {
        // Normalmente no se usa en APIs
    }

    public function store(Request $request)
    {
        try {
            $anotacion = AnotacionesDisciplinarias::find($request->input('idAnotacionesDisciplinarias'));
            if (!$anotacion) {
                return response()->json(['message' => 'No se encontró la anotación disciplinaria'], 404);
            }
    
            $matricula = Matricula::find($anotacion->idEstudiante);
            if (!$matricula) {
                return response()->json(['message' => 'No se encontró la matrícula asociada a la anotación'], 404);
            }
    
            $idPersona = $matricula->idPersona;


            $user = KeyUtil::user();
            if (!$user || !$user->persona) {
                return response()->json(['message' => 'Token inválido o persona no encontrada'], 401);
            }
            $idPersonaLogueable = $user->persona->id;
            $contrato = Contract::where('idpersona', $idPersonaLogueable)->first();
            if (!$contrato) {
                return response()->json(['message' => 'No se encontró un contrato asociado al usuario autenticado'], 403);
            }

            $compromiso = new Compromiso();
            $compromiso->observacion = $request->input('observacion');
            $compromiso->fecha = $request->input('fecha');
            $compromiso->idAnotacionesDisciplinarias = $request->input('idAnotacionesDisciplinarias');
            $compromiso->idDocente = $contrato->id;
            $compromiso->saveFileanotaciones($request);
            $compromiso->save();

            $compromiso->notificarCompromisoEstudiante([$idPersona]);
    
             return response()->json($compromiso, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el compromiso', 'error' => $e->getMessage()], 500);
        }
    }
    


   

    public function update(Request $request, Compromiso $compromiso)
    {
        $validatedData = $request->validate([
            'observacion' => 'nullable|string|max:255',
            'urlDocumento' => 'nullable|string|max:255',
            'fecha' => 'required|date',
            'idAnotacion' => 'required|exists:anotacionesDisciplinarias,id',
        ]);

        $compromiso->update($validatedData);

        return response()->json($compromiso);
    }

    public function destroy(Compromiso $compromiso)
    {
        $compromiso->delete();

        return response()->json([], 204);
    }

    public function commitmentsByAnotation($idAnotacion)
    {
        $compromisos = Compromiso::with('contrato.persona')
            ->where('idAnotacionesDisciplinarias', $idAnotacion)
            ->get();
    
        return response()->json($compromisos);
    }

    public function updateCumplido(Request $request)
    {

        $user = KeyUtil::user();
        $idPersona = $user->persona->id;
        $contrato = Contract ::where('idpersona', $idPersona)->first();
        $idContrato = $contrato->id;
        $request->validate([
            'itemId' => 'required|exists:compromisos,id',
            'checkState' => 'required|boolean',
        ]);

        $idContrato = $contrato->id;
        $compromiso = Compromiso::findOrFail($request->itemId);
        $compromiso->cumplido = $request->checkState ? 1 : 0;
        $compromiso->idDocente = $idContrato;
        $compromiso->save();

        return response()->json([
            'message' => 'Compromiso actualizado correctamente',
            'compromiso' => $compromiso,
        ]);
    }

}
