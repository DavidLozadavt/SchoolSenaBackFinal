<?php

namespace App\Http\Controllers\gestion_transporte;

use App\Util\KeyUtil;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transporte\ObservacionViaje;

class ObservacionViajeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $idViaje = $request->input('idViaje');
        
        $observacionesViaje = ObservacionViaje::where('idViaje', $idViaje)
            ->with('user.persona')
            ->orderBy('created_at', 'desc') 
            ->get();
        
        return response()->json($observacionesViaje);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
        $observacionViaje = new ObservacionViaje();
        $observacionViaje->observacion = $request->input('observacion');
        $observacionViaje->idViaje = $request->input('idViaje');
        $observacionViaje->idUser = KeyUtil::user()->id;
        $observacionViaje->save();
        return response()->json($observacionViaje, 201);
    
    }

    /**
     * Display the specified resource.
     *
     * @param  ObservacionViaje  $observacionViaje
     * @return \Illuminate\Http\Response
     */
    public function show(ObservacionViaje $observacionViaje)
    {

        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  ObservacionViaje  $observacionViaje
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ObservacionViaje $observacionViaje)
    {

        $observacionViaje->observacion = $request->input('observacion');
        $observacionViaje->idViaje = $request->input('idViaje');
        $observacionViaje->idUser = KeyUtil::user()->id;
        $observacionViaje->save();
        return response()->json($observacionViaje);
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  ObservacionViaje  $observacionViaje
     * @return \Illuminate\Http\Response
     */
    public function destroy(string|int $id)
    {
        $observacionViaje = ObservacionViaje::findOrFail($id);
        $observacionViaje->delete();
        return response()->json(null, 204);
    }
}
