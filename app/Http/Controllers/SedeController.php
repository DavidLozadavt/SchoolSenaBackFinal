<?php

namespace App\Http\Controllers;

use App\Models\CentrosFormacion;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SedeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1️⃣ Limpiar nombre (bonito)
        $nombreOriginal = preg_replace(
            '/\s+/',
            ' ',
            trim($request->nombre)
        );

        // 2️⃣ Normalizar para comparación
        $nombreNormalizado = mb_strtolower($nombreOriginal);

        // 3️⃣ Verificación manual (case-insensitive)
        $existe = Sede::whereRaw('LOWER(nombre) = ?', [$nombreNormalizado])
            ->where('idCentroFormacion', $request->idCentroFormacion)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya existe una sede con ese nombre en este centro de formación.'
            ], 422);
        }

        // 4️⃣ Validación normal
        $validated = $request->validate([
            'nombre'       => 'required|string|max:100',
            'jefeInmediato'  => 'nullable|string|max:100',
            'descripcion'   => 'nullable|string',
            'idCiudad'      => 'nullable|exists:ciudad,id',
            'idEmpresa'      => 'nullable|exists:empresa,id',
            'direccion'     => 'nullable|string|max:200',
            'email'         => 'nullable|email|max:100',
            'telefono'      => 'nullable|string|max:100',
            'celular'       => 'nullable|string|max:100',
            'idResponsable' => 'nullable|exists:usuario,id',
            'idCentroFormacion' => 'required|exists:centrosformacion,id',
            'imagen'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // 5️⃣ Usar el nombre limpio y bonito
        $validated['nombre'] = $nombreOriginal;

        // 6️⃣ Imagen
        $urlImagen = Sede::RUTA_FOTO_DEFAULT;

        if ($request->hasFile('imagen')) {
            $urlImagen = $request->file('imagen')->store('sedes', 'public');
        }

        $validated['urlImagen'] = $urlImagen;

        $sede = Sede::create($validated);

        return response()->json($sede, 201);
    }

    public function index()
    {
        $sedes = Sede::select()->whereNotNull('idCiudad')->with([
            'ciudad:id,descripcion',
            'empresa:id,razonSocial'
        ])->get();
        return response()->json($sedes);
    }
    public function getUsersSena()
    {
        $users = User::select()->with('persona')->get();
        return response()->json($users);
    }
    public function show($id)
    {
        $sede = Sede::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $sede
        ]);
    }
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre'       => [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::unique('sedes')->where(function ($query) use ($request) {
                        return $query->where('idCentroFormacion', $request->idCentroFormacion);
                    })
                ],
                'jefeInmediato'  => 'nullable|string|max:100',
                'descripcion'   => 'nullable|string',
                'idCiudad'      => 'nullable|exists:ciudad,id',
                'idEmpresa'      => 'nullable|exists:empresa,id',
                'direccion'     => 'nullable|string|max:200',
                'email'         => 'nullable|email|max:100',
                'telefono'      => 'nullable|string|max:100',
                'celular'       => 'nullable|string|max:100',
                'idResponsable' => 'nullable|exists:usuario,id',
                'imagen'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $sede = Sede::findOrFail($id);

            $sede->update($request->only([
                'nombre',
                'jefeInmediato',
                'descripcion',
                'direccion',
                'email',
                'telefono',
                'celular',
                'idResponsable',
                'idCiudad',
                'idEmpresa'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => '¡Sede actualizada correctamente!',
                'data' => $sede
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la sede de formación',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getSedesByRegional($idRegional)
    {
        $sedes = Sede::where('idEmpresa', $idRegional)
            ->with([
                'ciudad:id,descripcion',
                'empresa:id,razonSocial'
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $sedes
        ]);
    }
    public function getSedesByCentroFormacion($idCentroFormacion): JsonResponse
    {
        try {
            // Verificar que el centro de formación existe
            $centroFormacion = CentrosFormacion::find($idCentroFormacion);

            if (!$centroFormacion) {
                return response()->json([
                    'message' => 'Centro de formación no encontrado',
                    'data' => []
                ], 404);
            }

            // Obtener las sedes del centro de formación con sus relaciones
            $sedes = Sede::where('idCentroFormacion', $idCentroFormacion)
                ->with([
                    'centroFormacion',
                    'empresa',
                    'ciudad',
                    'responsable'
                ])
                ->orderBy('nombre', 'asc')
                ->get();

            return response()->json([
                'message' => 'Sedes obtenidas correctamente',
                'data' => $sedes,
                'centroFormacion' => $centroFormacion
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las sedes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function destroy($id): JsonResponse
{
    $sede = Sede::findOrFail($id);

    // Verificar si la sede está en uso
    if (
        $sede->fichas()->exists() ||
        $sede->ambientes()->exists()
    ) {
        return response()->json([
            'status'  => 'error',
            'message' => 'No se puede eliminar la sede porque tiene fichas o ambientes asociados.'
        ], 409);
    }

    $sede->delete();

    return response()->json([
        'status'  => 'success',
        'message' => 'Sede eliminada correctamente.'
    ], 200);
}

}
