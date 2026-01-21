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
                    'between:1,9',
                ],
                'idCiudad' => 'nullable|exists:ciudad,id',
            ]);

            $nuevaRegional = Company::create([
                'razonSocial' => $request->razonSocial,
                'nit' => $request->nit,
                'representanteLegal' => $request->representanteLegal,
                'direccion' => $request->direccion,
                'email' => $request->email,
                'rutaLogo' => Company::RUTA_LOGO_DEFAULT,
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
            ]);

            $regional = Company::findOrFail($id);

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
}
