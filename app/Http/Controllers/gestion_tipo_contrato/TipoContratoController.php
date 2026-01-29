<?php

namespace App\Http\Controllers\gestion_tipo_contrato;

use App\Http\Controllers\Controller;
use App\Http\Controllers\destion_tipo_contrato\TipoContrato;
use App\Models\ContractType;
use App\Models\Proceso;
use Illuminate\Http\Request;

class TipoContratoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return ContractType::get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $tipoContrato = new ContractType();
        $tipoContrato->nombreTipoContrato = $request->input('nombreTipoContrato');
        $tipoContrato->descripcion = $request->input('descripcion');
        $tipoContrato->save();

        // Verificar si ya existe un proceso con ese nombre antes de crear uno nuevo
        $proceso = Proceso::where('nombreProceso', $tipoContrato->nombreTipoContrato)->first();
        
        if (!$proceso) {
            $proceso = new Proceso();
            $proceso->nombreProceso = $tipoContrato->nombreTipoContrato;
            $proceso->descripcion = $tipoContrato->descripcion;
            $proceso->save();
        }

        return response()->json($tipoContrato, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $tipoContrato = ContractType::find($id);
        return response()->json($tipoContrato);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $tipoContrato = ContractType::findOrFail($id);
        $nombreAnterior = $tipoContrato->nombreTipoContrato;
        
        $tipoContrato->nombreTipoContrato = $request->input('nombreTipoContrato');
        $tipoContrato->descripcion = $request->input('descripcion');
        $tipoContrato->save();

        // Actualizar o crear el proceso correspondiente
        $proceso = Proceso::where('nombreProceso', $nombreAnterior)->first();
        
        if ($proceso) {
            // Si existe, actualizarlo
            $proceso->nombreProceso = $tipoContrato->nombreTipoContrato;
            $proceso->descripcion = $tipoContrato->descripcion;
            $proceso->save();
        } else {
            // Si no existe, crearlo
            $proceso = new Proceso();
            $proceso->nombreProceso = $tipoContrato->nombreTipoContrato;
            $proceso->descripcion = $tipoContrato->descripcion;
            $proceso->save();
        }

        return response()->json($tipoContrato);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $tipoContrato = ContractType::findOrFail($id);
        $tipoContrato->delete();

        return response()->json([], 204);
    }
}
