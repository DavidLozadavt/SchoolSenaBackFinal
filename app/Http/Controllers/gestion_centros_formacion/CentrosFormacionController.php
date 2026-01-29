<?php

namespace App\Http\Controllers\gestion_centros_formacion;

use App\Http\Controllers\Controller;
use App\Models\CentrosFormacion;
use Illuminate\Http\Request;

class CentrosFormacionController extends Controller
{
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'direccion' => 'required|string|max:255',
                'telefono' => 'required|string|max:20',
                'correo' => 'required|string|max:255',
                'subdirector' => 'required|string|max:255',
                'correosubdirector' => 'required|string|max:255',
                'idCiudad' => 'required|exists:ciudad,id',
                'idEmpresa' => 'required|exists:empresa,id',
            ]);

            $nuevoCentro = CentrosFormacion::create([
                'nombre' => $request->nombre,
                'direccion' => $request->direccion,
                'telefono' => $request->telefono,
                'correo' => $request->correo,
                'subdirector' => $request->subdirector,
                'correosubdirector' => $request->correosubdirector,
                'idCiudad' => $request->idCiudad,
                'idEmpresa' => $request->idEmpresa,
            ]);
            return response()->json([
                'status' => 'success',
                'message' => '¡Centro de formación guardado con éxito!',
                'data' => $nuevoCentro
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar el centro de formación: ' . $e->getMessage()
            ], 500);
        }
    }
    public function index()
    {
        $centros = CentrosFormacion::select(
            'id',
            'nombre',
            'direccion',
            'telefono',
            'correo',
            'subdirector',
            'correosubdirector',
            'idCiudad',
            'idEmpresa'
        )
            ->whereNotNull('idCiudad')
            ->whereNotNull('idEmpresa')
            ->with([
                'ciudad:id,descripcion',
                'empresa:id,razonSocial'
            ])

            ->get();

        return response()->json($centros);
    }
    public function show($id)
    {
        try {
            $centroFormacion = CentrosFormacion::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $centroFormacion
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
                'nombre' => 'sometimes|string|max:255',
                'direccion' => 'sometimes|string|max:255',
                'telefono' => 'sometimes|string|max:20',
                'correo' => 'sometimes|email|max:255',
                'subdirector' => 'sometimes|string|max:255',
                'correosubdirector' => 'sometimes|email|max:255',
                'idCiudad' => 'sometimes|exists:ciudad,id',
                'idEmpresa' => 'sometimes|exists:empresa,id',
            ]);

            $centro = CentrosFormacion::findOrFail($id);

            $centro->update($request->only([
                'nombre',
                'direccion',
                'telefono',
                'correo',
                'subdirector',
                'correosubdirector',
                'idCiudad',
                'idEmpresa'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => '¡Centro de formación actualizado correctamente!',
                'data' => $centro
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el centro de formación',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function showCentrosByRegional($idRegional)
    {
        $centros = CentrosFormacion::select(
            'id',
            'nombre',
            'idEmpresa',
            'idCiudad'
        )
            ->where('idEmpresa', $idRegional)
            ->with([
                'ciudad:id,descripcion',
                'empresa:id,razonSocial'
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Centros de formación obtenidos correctamente',
            'data' => $centros
        ]);
    }
}
