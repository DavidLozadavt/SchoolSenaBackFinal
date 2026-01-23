<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Models\User;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1️⃣ Validación
        $validated = $request->validate([
            'nombre'         => 'required|string|max:100',
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

        $urlImagen = Sede::RUTA_FOTO_DEFAULT;

        if ($request->hasFile('imagen')) {
            $urlImagen = $request
                ->file('imagen')
                ->store('sedes', 'public');
        }

        $validated['urlImagen'] = $urlImagen;

        $sede = Sede::create($validated);

        return response()->json($sede, 201);
    }
    public function getUsersSena()
    {
        $users = User::select()->with('persona')->get();
        return response()->json($users);
    }
}
