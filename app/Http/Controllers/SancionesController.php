<?php

namespace App\Http\Controllers;

use App\Models\AnotacionesDisciplinarias;
use App\Models\Contract;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Models\Status;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class SancionesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        $user = KeyUtil::user();
        $idPersona = $user->persona->id;
        $contrato = Contract::where('idpersona', $idPersona)->first();
        $idContrato = $contrato->id;
        $anotacion = AnotacionesDisciplinarias::find($request->input('idAnotacionesDisciplinarias'));
        if (!$anotacion) {
            return response()->json(['message' => 'No se encontró la anotación disciplinaria'], 404);
        }

        $matricula = Matricula::find($anotacion->idEstudiante);
        if (!$matricula) {
            return response()->json(['message' => 'No se encontró la matrícula asociada a la anotación'], 404);
        }

        $idPersona = $matricula->idPersona;


        $existingSancion = Sancion::where('idAnotacionesDisciplinarias', $request->input('idAnotacionesDisciplinarias'))->first();
    
        if ($existingSancion) {
            return response()->json([
                'message' => 'Ya existe una sanción para esta anotación disciplinaria.'
            ], 409); // 409 Conflict
        }
    
        $sancion = new Sancion();
        $sancion->observacion = $request->input('observacion');
        $sancion->fechaInicial = $request->input('fechaInicial');
        $sancion->fechaFinal = $request->input('fechaFinal');
        $sancion->idAnotacionesDisciplinarias = $request->input('idAnotacionesDisciplinarias');
        $sancion->gradoSancion = $request->input('gradoSancion');
        $sancion->idEstado = Status::ID_ACTIVE;
        $sancion->idDocente = $idContrato;
        $sancion->saveFileSanctions($request);
        $sancion->save();
        $sancion->notificarsancionEstudiante([$idPersona]);
    
        return response()->json($sancion, 201);
    }
    

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Sancion  $sancion
     * @return \Illuminate\Http\Response
     */
  

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Sancion  $sancion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $sancion = Sancion::findOrFail($id);
        $sancion->observacion = $request->input('observacion');
        $sancion->fechaInicial = $request->input('fechaInicial');
        $sancion->fechaFinal = $request->input('fechaFinal');
        $sancion->gradoSancion=$request->input('gradoSancion');
        $sancion->idAnotacionesDisciplinarias = $request->input('idAnotacionesDisciplinarias');
        $sancion->idEstado = Status::ID_ACTIVE;
        $sancion->saveFileSanctions($request);
        $sancion->save();
        return response()->json($sancion, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Sancion  $sancion
     * @return \Illuminate\Http\Response
     */
    public function destroy(Sancion $sancion)
    {
        //
    }

        public function getPenaltiesforannotations($idAnotacion){
            $sanciones = Sancion::with('contrato.persona')
            ->where('idAnotacionesDisciplinarias', $idAnotacion)
            ->get();
            return response()->json($sanciones);

        

        }
}

