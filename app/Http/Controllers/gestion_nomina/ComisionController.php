<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\Comision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComisionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $comisiones = Comision::all();
        return response()->json($comisiones);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $comision = Comision::create($request->all());
        return response()->json($comision, 201);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {

        $comision = Comision::findOrFail($id);
        $comision->update($request->all());

        return response()->json($comision, 200);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $comision = Comision::find($id);

        if (!$comision) {
            return response()->json(['error' => 'Comisión no encontrada'], 404);
        }
        $comision->delete();

        return response()->json(['message' => 'Comisión eliminada']);
    }
}
