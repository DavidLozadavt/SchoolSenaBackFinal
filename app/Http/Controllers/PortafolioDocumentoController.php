<?php

namespace App\Http\Controllers;

use App\Models\PortafolioDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PortafolioDocumentoController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => PortafolioDocumento::with('portafolioFicha')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descripcion' => 'required|string',
            'idPortafolioFichas' => 'required|exists:portafolioFichas,id',
            'documento' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $urlDocumento = null;

        if ($request->hasFile('documento')) {
            $path = $request->file('documento')->store('portafolio-documentos', 'public');
            $urlDocumento = 'storage/' . $path;
        }

        $documento = PortafolioDocumento::create([
            'descripcion' => $request->descripcion,
            'idPortafolioFichas' => $request->idPortafolioFichas,
            'urlDocumento' => $urlDocumento,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Documento creado correctamente.',
            'data' => $documento,
        ], 201);
    }

    public function show($id)
    {
        $documento = PortafolioDocumento::with('portafolioFicha')->find($id);

        if (!$documento) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        return response()->json(['success' => true, 'data' => $documento]);
    }

    public function update(Request $request, $id)
    {
        $documento = PortafolioDocumento::find($id);

        if (!$documento) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'descripcion' => 'sometimes|string',
            'documento' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('documento')) {
            // Eliminar archivo anterior si existe
            if ($documento->urlDocumento) {
                Storage::disk('public')->delete($documento->urlDocumento);
            }

            $path = $request->file('documento')->store('portafolio-documentos', 'public');
            $documento->urlDocumento = 'storage/' . $path;
        }

        if ($request->filled('descripcion')) {
            $documento->descripcion = $request->descripcion;
        }

        $documento->save();

        return response()->json([
            'success' => true,
            'message' => 'Documento actualizado correctamente.',
            'data' => $documento,
        ]);
    }

    public function destroy($id)
    {
        $documento = PortafolioDocumento::find($id);

        if (!$documento) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        // Eliminar archivo físico
        if ($documento->urlDocumento) {
            Storage::disk('public')->delete($documento->urlDocumento);
        }

        $documento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento eliminado correctamente.',
        ]);
    }
}