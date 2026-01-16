<?php

namespace App\Http\Controllers\gestion_proceso;

use App\Http\Controllers\Controller;
use App\Models\Proceso;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class ProcesoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $nombreProceso = $request->input('nombreProceso');

        $procesos = Proceso::query();
        if ($nombreProceso) {
            $procesos->where('nombreProceso', $nombreProceso);
        }


        return response()->json($procesos->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->all();
        
        $data['idCompany'] = KeyUtil::idCompany();
        $data['tipoDocumento'] = $request->input('tipoDocumento'); 
    
       
        $proceso = new Proceso($data);
        $proceso->save();
    
        return response()->json($proceso, 201);
    }
    

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $proceso = Proceso::find($id);

        return response()->json($proceso);
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
        $proceso = Proceso::findOrFail($id);
        $proceso->fill($data);
        $proceso->save();

        return response()->json($proceso);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $proceso = Proceso::findOrFail($id);
        $proceso->delete();

        return response()->json([], 204);
    }
}
