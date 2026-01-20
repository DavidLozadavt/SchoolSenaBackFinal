<?php

namespace App\Http\Controllers\gestion_centros_formacion;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CentrosFormacionController extends Controller
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
                'idRegional' => 'required|exists:regional,id',
                'direccion' => 'required|string|max:255',
                'email' => 'required|string|max:255',
                'digitoVerificacion' => [
                    'required',
                    'integer',
                    'digits:5',
                    Rule::unique('empresa', 'digitoVerificacion'),
                ],
            ]);

            $nuevoCentro = Company::create([
                'razonSocial' => $request->razonSocial,
                'nit' => $request->nit,
                'representanteLegal' => $request->representanteLegal,
                'idRegional' => $request->idRegional,
                'direccion' => $request->direccion,
                'email' => $request->email,
                'rutaLogo' => Company::RUTA_LOGO_DEFAULT,
                'digitoVerificacion' => $request->digitoVerificacion
            ]);
            return response()->json([
                'status' => 'success',
                'message' => '¡Centro guardado con éxito!',
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
        $centros = Company::select(
            'id',
            'razonSocial',
            'nit',
            'representanteLegal',
            'digitoverificacion',
            'direccion',
            'email',
            'idRegional'
        )
            ->whereNotNull('idRegional')
            ->with([
                'regional' => function ($query) {
                    $query->select('id', 'nombre');
                }
            ])
            ->get();

        return response()->json($centros);
    }
}
