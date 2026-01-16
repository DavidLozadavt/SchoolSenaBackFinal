<?php

namespace App\Http\Controllers\gestion_tipopago;

use App\Http\Controllers\Controller;
use App\Models\TipoPago;
use Illuminate\Http\Request;

class TipoPagoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tipoPagos = TipoPago::all();

        return response()->json($tipoPagos);
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
        $tipoPago = new TipoPago($data);
        $tipoPago->save();

        return response()->json($tipoPago, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $tipoPago = TipoPago::find($id);

        return response()->json($tipoPago);
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
        $tipoPago = TipoPago::findOrFail($id);
        $tipoPago->fill($data);
        $tipoPago->save();

        return response()->json($tipoPago);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $tipoPago = TipoPago::findOrFail($id);
        $tipoPago->delete();

        return response()->json([], 204);
    }
}
