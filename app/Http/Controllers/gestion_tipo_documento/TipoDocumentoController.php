<?php

namespace App\Http\Controllers\gestion_tipo_documento;

use App\Http\Controllers\Controller;
use App\Models\AsignacionProcesoTipoDocumento;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;

class TipoDocumentoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $estado = $request->input('estado');
        $proceso = $request->input('proceso');
        $tipoDocumentos = AsignacionProcesoTipoDocumento::with('proceso' , 'tipoDocumento.estado');

        if ($estado) {
            $tipoDocumentos->whereHas('estado', function ($q) use ($estado) {
                return $q->select('id')->where('id', $estado)->orWhere('estado', $estado);
            });
        }

        if ($proceso) {
            $tipoDocumentos->whereHas('proceso', function ($q) use ($proceso) {
                return $q->select('id')->where('id', $proceso)->orWhere('nombreProceso', $proceso);
            });
        }

        return response()->json($tipoDocumentos->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
     

        $tipoDocumento = new TipoDocumento();
        $tipoDocumento->tituloDocumento = $request->input('tituloDocumento');
        $tipoDocumento->descripcion = $request->input('descripcion');
        $tipoDocumento->idEstado = $request->input('idEstado');
        $tipoDocumento->tipoFecha = $request->input('fechaTipo');
        $tipoDocumento->save();

        $asignacion = new AsignacionProcesoTipoDocumento();
        $asignacion->idTipoDocumento= $tipoDocumento->id;
        $asignacion->idProceso = $request->input('idProceso');

        $asignacion->save();

        return response()->json($asignacion, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $tipoDocumento = TipoDocumento::find($id);

        return response()->json($tipoDocumento);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $data = $request->all();
        
        $asignacion = AsignacionProcesoTipoDocumento::where('id', $id)->firstOrFail();
    
       
        $tipoDocumento = TipoDocumento::findOrFail($asignacion->idTipoDocumento);
        
        $tipoDocumento->tituloDocumento = $data['tituloDocumento'];
        $tipoDocumento->descripcion = $data['descripcion'];
     
        $tipoDocumento->save();
        
       
        $asignacion->idProceso = $data['idProceso'];
        $asignacion->save();
        
        return response()->json([
            'tipoDocumento' => $tipoDocumento,
            'asignacion' => $asignacion
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
     
        AsignacionProcesoTipoDocumento::where('idTipoDocumento', $id)->delete();
        
    
        $tipoDocumento = TipoDocumento::findOrFail($id);
        $tipoDocumento->delete();
    
        return response()->json([], 204);
    }
}
