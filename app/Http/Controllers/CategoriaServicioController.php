<?php

namespace App\Http\Controllers;

use App\Models\CategoriaServicio;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaServicioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $idCompany = KeyUtil::idCompany();

        $data = CategoriaServicio::where('idCompany', $idCompany)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($data);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $idCompany = KeyUtil::idCompany();

        $data = $request->all();
        $data['idCompany'] = $idCompany;

        $categoriaServicio = CategoriaServicio::create($data);

        return response()->json($categoriaServicio, 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(CategoriaServicio $categoriaServicio)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $categoriaServicio = CategoriaServicio::findOrFail($id);
        $categoriaServicio->update($request->all());
        return response()->json($categoriaServicio, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $categoriaServicio = CategoriaServicio::findOrFail($id);
        $categoriaServicio->delete();
        return response()->json(null, 204);
    }



    public function getCategoriesWebPage($id)
    {

        $data = CategoriaServicio::where('idCompany', $id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($data);
    }
}
