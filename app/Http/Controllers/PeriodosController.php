<?php

namespace App\Http\Controllers;

use App\Models\Periodo;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class PeriodosController extends Controller
{
    public function index()
    {
        $periodos = Periodo::orderBy('fechaInicial', 'desc')->get();

        return response()->json(
            $periodos
        , 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombrePeriodo' => 'required|string|max:255',
            'fechaInicial'  => 'required|date',
            'fechaFinal'    => 'required|date|after_or_equal:fechaInicial',
        ]);

        $periodo = Periodo::create([
            'nombrePeriodo' => $request->nombrePeriodo,
            'fechaInicial'  => $request->fechaInicial,
            'fechaFinal'    => $request->fechaFinal,
            'idEmpresa'     => KeyUtil::idCompany()
        ]);

        return response()->json([
            'message' => 'Periodo creado correctamente',
            'data'    => $periodo
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombrePeriodo' => 'required|string|max:255',
            'fechaInicial'  => 'required|date',
            'fechaFinal'    => 'required|date|after_or_equal:fechaInicial',
        ]);

        $periodo = Periodo::findOrFail($id);

        $periodo->update([
            'nombrePeriodo' => $request->nombrePeriodo,
            'fechaInicial'  => $request->fechaInicial,
            'fechaFinal'    => $request->fechaFinal,
        ]);

        return response()->json([
            'message' => 'Periodo actualizado correctamente',
            'data'    => $periodo
        ], 200);
    }

    public function destroy($id)
    {
        $periodo = Periodo::findOrFail($id);
        $periodo->delete();

        return response()->json([
            'message' => 'Periodo eliminado correctamente'
        ], 200);
    }
}
