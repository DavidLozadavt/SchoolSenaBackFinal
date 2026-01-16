<?php

namespace App\Http\Controllers\gestion_productos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategoriasProController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categorias = Category::withCount('productos')->get();

        return response()->json($categorias);
    }


    public function getAllCategories()
    {
        $idCompany = KeyUtil::idCompany();

        $categorias = Category::withCount('productos')
            ->where('idCompany', $idCompany)
            ->get();

        return response()->json($categorias);
    }



    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {


        DB::beginTransaction();
        $idCompany = KeyUtil::idCompany();
        try {
            $categoria = new Category();
            $categoria->nombre = $request->input('nombre');
            $categoria->descripcion = $request->input('descripcion');
            $categoria->idCompany = $idCompany;
            $categoria->url = $this->storeUrlCategory($request);
            $categoria->save();

            DB::commit();

            return response()->json($categoria, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    private function storeUrlCategory(Request $request, $default = true)
    {
        $url = null;

        if ($default) {
            $url = Category::RUTA_CATEGORY_DEFAULT;
        }
        if ($request->hasFile('url')) {
            $url =
                '/storage/' .
                $request
                ->file('url')
                ->store(Category::RUTA_CATEGORY, ['disk' => 'public']);
        }
        return $url;
    }




    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $categoria = Category::findOrFail($id);

            $categoria->nombre = $request->input('nombre', $categoria->nombre);
            $categoria->descripcion = $request->input('descripcion', $categoria->descripcion);

            if ($request->hasFile('url')) {

                if ($categoria->url && strpos($categoria->url, '/storage/') === 0) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $categoria->url));
                }

                $categoria->url = $this->storeUrlCategory($request);
            }

            $categoria->save();

            DB::commit();

            return response()->json($categoria, 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {

        $categoria = Category::findOrFail($id);

        if ($categoria->productos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados.'
            ], 400);
        }


        $categoria->delete();

        return response()->json([
            'message' => 'Categoría eliminada exitosamente.'
        ], 204);
    }


    public function getAllCategoriesWebPage($id)
    {


        $categorias = Category::where('idCompany', $id)
            ->get();

        return response()->json($categorias);
    }
}
