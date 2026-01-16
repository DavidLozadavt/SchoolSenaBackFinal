<?php

namespace App\Http\Controllers\gestion_ventas;

use App\Http\Controllers\Controller;
use App\Models\TipoArticulo;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class TipoArticuloController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $idCompany =  KeyUtil::idCompany();
        $tipoArticulo = TipoArticulo::where('idCompany', $idCompany)->get();
        return response()->json($tipoArticulo);
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

        $idCompany = KeyUtil::idCompany();

        $tipoArticulo = new TipoArticulo($data);
        $tipoArticulo->idCompany = $idCompany;
        $tipoArticulo->save();
      
        return response()->json($tipoArticulo, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = $request->all();
        $tipoArticulo = TipoArticulo::findOrFail($id);
        $tipoArticulo->fill($data);
        $tipoArticulo->save();

        return response()->json($tipoArticulo);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $tipoArticulo = TipoArticulo::findOrFail($id);
        $tipoArticulo->delete();

        return response()->json([], 204);
    }
}
