<?php

namespace App\Http\Controllers\gestion_mediopago;

use App\Http\Controllers\Controller;
use App\Models\MedioPago;
use Illuminate\Http\Request;

class MedioPagoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $medioPagos = MedioPago::all();

        return response()->json($medioPagos);
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
        $medioPago = new MedioPago($data);
        $medioPago->save();

        return response()->json($medioPago, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $medioPago = MedioPago::find($id);

        return response()->json($medioPago);
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
        $medioPago = MedioPago::findOrFail($id);
        $medioPago->fill($data);
        $medioPago->save();

        return response()->json($medioPago);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $medioPago = MedioPago::findOrFail($id);
        $medioPago->delete();

        return response()->json([], 204);
    }
}
