<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Red;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RedController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $rutaDocumento = null;

        if ($request->hasFile('foto')) {
            $rutaDocumento = '/storage/' . $request
                ->file('foto')
                ->store('red/imagen', 'public');
        }

        $validated['foto'] = $rutaDocumento ?? Company::RUTA_LOGO_DEFAULT;

        $red = Red::create($validated);

        return response()->json($red, 201);
    }

    public function index()
    {
        $redes = Red::all();
        return response()->json($redes);
    }

    public function show($id)
    {
        $red = Red::findOrFail($id);
        return response()->json($red);
    }

    public function update(Request $request, $id)
    {
        $red = Red::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        // Si se envía una nueva imagen
        if ($request->hasFile('foto')) {

            // Eliminar imagen anterior si no es la default
            if ($red->foto && $red->foto !== Company::RUTA_LOGO_DEFAULT) {
                $rutaAnterior = str_replace('/storage/', '', $red->foto);
                Storage::disk('public')->delete($rutaAnterior);
            }

            $rutaNueva = '/storage/' . $request
                ->file('foto')
                ->store('red/imagen', 'public');

            $validated['foto'] = $rutaNueva;
        }

        $red->update($validated);

        return response()->json($red);
    }

    public function destroy($id)
    {
        $red = Red::withCount('programas')->findOrFail($id);

        // Verificar si tiene programas asociados
        if ($red->programas_count > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la red porque tiene programas asociados.'
            ], 409); // 409 Conflict
        }

        // Eliminar imagen si no es la default
        if ($red->foto && $red->foto !== Red::RUTA_FOTO_DEFAULT) {
            $ruta = str_replace('/storage/', '', $red->getRawOriginal('foto'));
            Storage::disk('public')->delete($ruta);
        }

        $red->delete();

        return response()->json([
            'message' => 'Red eliminada correctamente'
        ]);
    }
}
