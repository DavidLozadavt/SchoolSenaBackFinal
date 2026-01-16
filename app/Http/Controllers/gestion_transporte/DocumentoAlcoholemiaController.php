<?php

namespace App\Http\Controllers\gestion_transporte;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transporte\DocumentoAlcoholemia;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DocumentoAlcoholemiaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        return response()->json(DocumentoAlcoholemia::all());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $path = $request
                ->file('file')
                ->store('documentos_alcoholemia', ['disk' => 'public']);
            $request->request->add(['documento' => $path]);
        }

        $request->request->add(['idConductor' => $request->idConductor ?? KeyUtil::lastContractActive()->id]);
        $document = DocumentoAlcoholemia::create($request->all());
        return response()->json($document, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  DocumentoAlcoholemia  $documentoAlcoholemia
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $document = DocumentoAlcoholemia::findOrFail($id);
        return response()->json($document);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  DocumentoAlcoholemia  $documentoAlcoholemia
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $document = DocumentoAlcoholemia::findOrFail($id);

        if ($request->hasFile('file')) {
            if ($document->documento) {
                Storage::disk('public')->delete($document->documento);
            }
            $path = $request
                ->file('file')
                ->store('documentos_alcoholemia', ['disk' => 'public']);
            $request->request->add(['documento' => $path]);
        }

        $document->update($request->all());
        return response()->json($document);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  DocumentoAlcoholemia  $documentoAlcoholemia
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $document = DocumentoAlcoholemia::findOrFail($id);
        if ($document->documento) {
            Storage::disk('public')->delete($document->documento);
        }
        $document->delete();
        return response()->json(null, 204);
    }
}
