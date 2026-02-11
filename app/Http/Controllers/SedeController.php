<?php

namespace App\Http\Controllers;

use App\Models\CentrosFormacion;
use App\Models\Company;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'idCentroFormacion' => 'required|exists:centroFormacion,id',
            'urlImagen'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $rutaDocumento = null;

        if ($request->hasFile('urlImagen')) {
            $rutaDocumento = '/storage/' . $request
                ->file('urlImagen')
                ->store('sede/imagen', 'public');
        }
        $validated['nombre'] = $nombreOriginal;

        $validated['urlImagen'] = $rutaDocumento ?? Company::RUTA_LOGO_DEFAULT;

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
        $sede = Sede::with([
            'ciudad:id,descripcion',
            'empresa:id,razonSocial',
            'centroFormacion:id,nombre',
            'responsable.persona:id,nombre1,apellido1,identificacion'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $sede
        ]);
    }
    public function update(Request $request, $id)
    {
        try {
            $sede = Sede::findOrFail($id);

            // Verificación case-insensitive
            if ($request->has('nombre')) {
                $nombreNormalizado = mb_strtolower(trim($request->nombre));

                $existe = Sede::whereRaw('LOWER(nombre) = ?', [$nombreNormalizado])
                    ->where('idCentroFormacion', $request->idCentroFormacion ?? $sede->idCentroFormacion)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($existe) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Ya existe una sede con ese nombre en este centro de formación.'
                    ], 422);
                }
            }

            $request->validate([
                'nombre'       => 'nullable|string|max:100',
                'jefeInmediato'  => 'nullable|string|max:100',
                'descripcion'   => 'nullable|string',
                'idCiudad'      => 'nullable|exists:ciudad,id',
                'idEmpresa'      => 'nullable|exists:empresa,id',
                'direccion'     => 'nullable|string|max:200',
                'email'         => 'nullable|email|max:100',
                'telefono'      => 'nullable|string|max:100',
                'celular'       => 'nullable|string|max:100',
                'idResponsable' => 'nullable|exists:usuario,id',
                'urlImagen'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            // Manejo de imagen
            if ($request->hasFile('urlImagen')) {
                // Eliminar imagen anterior si existe y no es la default
                if ($sede->urlImagen && $sede->urlImagen !== Company::RUTA_LOGO_DEFAULT) {
                    $rutaAnterior = str_replace('/storage/', '', $sede->urlImagen);
                    Storage::disk('public')->delete($rutaAnterior);
                }

                // Guardar nueva imagen
                $rutaDocumento = '/storage/' . $request
                    ->file('urlImagen')
                    ->store('sede/imagen', 'public');

                $sede->urlImagen = $rutaDocumento;
            }

            // Actualizar los demás campos
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

            // Refrescar para obtener los datos más recientes
            $sede->refresh();

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
        try {
            $sede = Sede::findOrFail($id);

            // Verificar relaciones
            $tieneRelaciones = false;
            $mensajeRelaciones = [];

            if ($sede->fichas()->exists()) {
                $tieneRelaciones = true;
                $cantidadFichas = $sede->fichas()->count();
                $mensajeRelaciones[] = "fichas ({$cantidadFichas})";
            }

            if ($sede->ambientes()->exists()) {
                $tieneRelaciones = true;
                $cantidadAmbientes = $sede->ambientes()->count();
                $mensajeRelaciones[] = "ambientes ({$cantidadAmbientes})";
            }

            if ($tieneRelaciones) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No se puede eliminar la sede porque tiene registros relacionados: ' .
                        implode(', ', $mensajeRelaciones) . '. Debe eliminar primero estos registros.'
                ], 400);
            }

            // Eliminar imagen si existe y no es la default
            if ($sede->urlImagen && $sede->urlImagen !== Company::RUTA_LOGO_DEFAULT) {
                $ruta = str_replace('/storage/', '', $sede->urlImagen);
                Storage::disk('public')->delete($ruta);
            }

            $sede->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Sede eliminada correctamente.'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '23000') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede eliminar la sede porque tiene registros relacionados'
                ], 400);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Error de base de datos al eliminar la sede'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar la sede'
            ], 500);
        }
    }
}
