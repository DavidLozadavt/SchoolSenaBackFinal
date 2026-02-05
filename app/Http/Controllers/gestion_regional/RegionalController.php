<?php

namespace App\Http\Controllers\gestion_regional;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class RegionalController extends Controller
{
    public function store(Request $request)
    {
        try {
            $request->validate([
                'razonSocial' => 'required|string|max:255',
                'nit' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('empresa', 'nit'),
                ],
                'representanteLegal' => 'required|string|max:255',
                'direccion' => 'required|string|max:255',
                'email' => 'required|string|max:255',
                'digitoVerificacion' => [
                    'required',
                    'integer',
                    'between:1,9',
                ],
                'idCiudad' => 'nullable|exists:ciudad,id',
                'rutaLogo' => 'nullable|file|mimes:png,jpg,jpeg'
            ]);

            $rutaDocumento = null;

            if ($request->hasFile('rutaLogo')) {
                $rutaDocumento = '/storage/' . $request
                    ->file('rutaLogo')
                    ->store('company/imagen', 'public');
            }


            $nuevaRegional = Company::create([
                'razonSocial' => $request->razonSocial,
                'nit' => $request->nit,
                'representanteLegal' => $request->representanteLegal,
                'direccion' => $request->direccion,
                'email' => $request->email,
                'rutaLogo' => $rutaDocumento ?? Company::RUTA_LOGO_DEFAULT,
                'digitoVerificacion' => $request->digitoVerificacion,
                'idCiudad' => $request->idCiudad,
            ]);
            return response()->json([
                'status' => 'success',
                'message' => '¡Regional guardada con éxito!',
                'data' => $nuevaRegional
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar la regional: ' . $e->getMessage()
            ], 500);
        }
    }
    public function index()
    {
        $regionales = Company::select('id', 'razonSocial', 'nit', 'rutaLogo', 'representanteLegal', 'digitoVerificacion', 'email', 'direccion', 'idCiudad')->whereNotNull('idCiudad')->with('ciudad');
        return response()->json($regionales->get());
    }
    public function show($id)
    {
        try {
            $regional = Company::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $regional
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Regional no encontrada'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'razonSocial' => 'sometimes|string|max:255',
                'nit' => [
                    'sometimes',
                    'string',
                    'max:255',
                    Rule::unique('empresa', 'nit')->ignore($id),
                ],
                'representanteLegal' => 'sometimes|string|max:255',
                'direccion' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'digitoVerificacion' => [
                    'required',
                    'integer',
                    'between:1,9',
                ],
                'idCiudad' => 'nullable|exists:ciudad,id',
                'rutaLogo' => 'nullable|file|mimes:png,jpg,jpeg' // Agregar validación
            ]);

            $regional = Company::findOrFail($id);

            // Manejar la carga del logo
            if ($request->hasFile('rutaLogo')) {
                // Opcional: Eliminar el logo anterior si existe y no es el default
                if ($regional->rutaLogo && $regional->rutaLogo !== Company::RUTA_LOGO_DEFAULT) {
                    $rutaAnterior = str_replace('/storage/', '', $regional->rutaLogo);
                    \Storage::disk('public')->delete($rutaAnterior);
                }

                // Guardar el nuevo logo
                $rutaDocumento = '/storage/' . $request
                    ->file('rutaLogo')
                    ->store('company/imagen', 'public');

                $regional->rutaLogo = $rutaDocumento;
            }

            // Actualizar los demás campos
            $regional->update($request->only([
                'razonSocial',
                'nit',
                'representanteLegal',
                'direccion',
                'email',
                'digitoVerificacion',
                'idCiudad'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => '¡Regional actualizada correctamente!',
                'data' => $regional
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la regional: ' . $e->getMessage()
            ], 500);
        }
    }
    public function destroy($id)
{
    try {
        $regional = Company::findOrFail($id);

        // Verificar si tiene registros relacionados en tablas hijas
        $tieneRelaciones = false;
        $mensajeRelaciones = [];

        // Verificar si tiene sedes
        if ($regional->sedes()->exists()) {
            $tieneRelaciones = true;
            $cantidadSedes = $regional->sedes()->count();
            $mensajeRelaciones[] = "sedes ({$cantidadSedes})";
        }

        // Verificar si tiene fichas (centros de formación)
        if ($regional->fichas()->exists()) {
            $tieneRelaciones = true;
            $cantidadFichas = $regional->fichas()->count();
            $mensajeRelaciones[] = "centros de formación ({$cantidadFichas})";
        }

        // Verificar si tiene configuración de nómina
        if ($regional->configuracionNomina()->exists()) {
            $tieneRelaciones = true;
            $mensajeRelaciones[] = "configuración de nómina";
        }

        // Verificar si tiene historial de configuraciones de nómina
        if ($regional->historialConfiguracionesNomina()->exists()) {
            $tieneRelaciones = true;
            $mensajeRelaciones[] = "historial de configuraciones";
        }

        if ($tieneRelaciones) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede eliminar la regional porque tiene registros relacionados: ' . 
                            implode(', ', $mensajeRelaciones) . '. Debe eliminar primero estos registros.'
            ], 400);
        }

        // Eliminar el logo si existe y no es el default
        if ($regional->rutaLogo && $regional->rutaLogo !== Company::RUTA_LOGO_DEFAULT) {
            $rutaAnterior = str_replace('/storage/', '', $regional->rutaLogo);
            Storage::disk('public')->delete($rutaAnterior);
        }

        // Eliminar la regional
        $regional->delete();

        return response()->json([
            'status' => 'success',
            'message' => '¡Regional eliminada correctamente!'
        ]);

    } catch (\Illuminate\Database\QueryException $e) {
        // Capturar errores de restricción de clave foránea
        if ($e->getCode() == '23000') {
            // Extraer el nombre de la tabla del mensaje de error
            $errorMessage = $e->getMessage();
            
            if (str_contains($errorMessage, 'centrosformacion')) {
                $detalle = 'centros de formación';
            } elseif (str_contains($errorMessage, 'sedes')) {
                $detalle = 'sedes';
            } elseif (str_contains($errorMessage, 'nomina')) {
                $detalle = 'configuraciones de nómina';
            } else {
                $detalle = 'otros registros';
            }

            return response()->json([
                'status' => 'error',
                'message' => "No se puede eliminar la regional porque tiene {$detalle} asociados. Debe eliminar primero estos registros."
            ], 400);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Error de base de datos al eliminar la regional'
        ], 500);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error inesperado al eliminar la regional'
        ], 500);
    }
}
}
