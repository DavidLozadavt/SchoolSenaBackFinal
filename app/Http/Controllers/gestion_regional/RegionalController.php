<?php

namespace App\Http\Controllers\gestion_regional;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
                'representanteLegal' => 'required|string|max:20',
                'direccion' => 'required|string|max:255',
                'email' => 'required|string|max:255',
                'digitoVerificacion' => [
                    'required',
                    'integer',
                    'digits:5',
                    Rule::unique('empresa', 'digitoVerificacion'),
                ],
            ]);

            $nuevaRegional = Company::create([
                'razonSocial' => $request->razonSocial,
                'nit' => $request->nit,
                'representanteLegal' => $request->representanteLegal,
                'direccion' => $request->direccion,
                'email' => $request->email,
                'rutaLogo' => Company::RUTA_LOGO_DEFAULT,
                'digitoVerificacion' => $request->digitoVerificacion
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
        $regionales = Company::select('id', 'razonSocial', 'nit', 'rutaLogo', 'representanteLegal', 'digitoVerificacion', 'email', 'telefono');
        return response()->json($regionales->get());
    }
    /**
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'telefono' => 'sometimes|string|max:20',
                'direccion' => 'sometimes|string|max:255',
            ]);

            $regional = Regional::findOrFail($id);

            $regional->update(
                $request->only(['nombre', 'telefono', 'direccion'])
            );

            return response()->json([
                'status' => 'success',
                'message' => '¡Regional actualizada correctamente!',
                'data' => $regional->load('departamento')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la regional: ' . $e->getMessage()
            ], 500);
        }
    }
    */
}
