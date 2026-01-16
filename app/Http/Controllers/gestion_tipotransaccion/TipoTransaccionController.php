<?php

namespace App\Http\Controllers\gestion_tipotransaccion;

use App\Http\Controllers\Controller;
use App\Models\TipoTransaccion;
use Illuminate\Http\Request;

class TipoTransaccionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tipoTransacciones = TipoTransaccion::all();

        return response()->json($tipoTransacciones);
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
        $tipoTransaccion = new TipoTransaccion($data);
        $tipoTransaccion->save();

        return response()->json($tipoTransaccion, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $tipoTransaccion = TipoTransaccion::find($id);

        return response()->json($tipoTransaccion);
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
        $tipoTransaccion = TipoTransaccion::findOrFail($id);
        $tipoTransaccion->fill($data);
        $tipoTransaccion->save();

        return response()->json($tipoTransaccion);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $tipoTransaccion = TipoTransaccion::findOrFail($id);
        $tipoTransaccion->delete();

        return response()->json([], 204);
    }
}
