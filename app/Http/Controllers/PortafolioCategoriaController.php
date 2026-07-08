<?php

namespace App\Http\Controllers;

use App\Models\PortafolioCategoria;
use Illuminate\Http\Request;

class PortafolioCategoriaController extends Controller
{
    public function index()
    {
        $categorias = PortafolioCategoria::where('activo', true)
            ->orderBy('orden')
            ->get();

        $categoriasPorId = $categorias->keyBy('id');

        $arbol = $categorias
            ->filter(fn($categoria) => is_null($categoria->idCategoriaPadre))
            ->values()
            ->map(function ($categoria) use ($categoriasPorId) {
                return $this->buildCategoryTree($categoria, $categoriasPorId);
            });

        return response()->json($arbol);
    }

    private function buildCategoryTree(PortafolioCategoria $categoria, $categoriasPorId)
    {
        $hijos = $categoriasPorId
            ->filter(fn($item) => (int) $item->idCategoriaPadre === (int) $categoria->id)
            ->values()
            ->map(function ($hijo) use ($categoriasPorId) {
                return $this->buildCategoryTree($hijo, $categoriasPorId);
            });

        $categoria->setRelation('hijos', $hijos);

        return $categoria;
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
            'idCategoriaPadre' => 'nullable|exists:portafolioCategorias,id'
        ]);

        $slug = \Str::slug($request->nombre);
        $count = PortafolioCategoria::where('slug', 'like', $slug . '%')->count();
        if ($count > 0) {
            $slug .= '-' . $count;
        }

        $categoria = PortafolioCategoria::create([
            'nombre' => $request->nombre,
            'slug' => $slug,
            'idCategoriaPadre' => $request->idCategoriaPadre,
            'orden' => PortafolioCategoria::where('idCategoriaPadre', $request->idCategoriaPadre)->max('orden') + 1,
            'activo' => true
        ]);

        return response()->json($categoria, 201);
    }
}
