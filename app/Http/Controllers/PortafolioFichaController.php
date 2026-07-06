<?php

namespace App\Http\Controllers;

use App\Models\PortafolioFicha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PortafolioFichaController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => PortafolioFicha::with(['ficha', 'portafolioDocumentos'])->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descripcion' => 'required|string',
            'idFicha' => 'required|exists:ficha,id',
            'idPortafolio' => 'required|exists:portafolio,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $fichaPortafolio = PortafolioFicha::create($request->only('descripcion', 'idFicha', 'idPortafolio'));

        return response()->json([
            'success' => true,
            'message' => 'Ficha asignada correctamente.',
            'data' => $fichaPortafolio->load('ficha'),
        ], 201);
    }

    public function show($id)
    {
        $fichaPortafolio = PortafolioFicha::with(['ficha', 'portafolioDocumentos'])->find($id);

        if (!$fichaPortafolio) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        return response()->json(['success' => true, 'data' => $fichaPortafolio]);
    }

    public function update(Request $request, $id)
    {
        $fichaPortafolio = PortafolioFicha::find($id);

        if (!$fichaPortafolio) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'descripcion' => 'sometimes|string',
            'idFicha' => 'sometimes|exists:ficha,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $fichaPortafolio->update($request->only('descripcion', 'idFicha'));

        return response()->json([
            'success' => true,
            'message' => 'Ficha actualizada correctamente.',
            'data' => $fichaPortafolio->load('ficha'),
        ]);
    }

    public function destroy($id)
    {
        $fichaPortafolio = PortafolioFicha::find($id);

        if (!$fichaPortafolio) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        $fichaPortafolio->delete();

        return response()->json(['success' => true, 'message' => 'Ficha desasignada correctamente.']);
    }
}