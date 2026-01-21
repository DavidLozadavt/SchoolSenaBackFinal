<?php

namespace App\Http\Controllers\gestion_sede_institucional;

use App\Http\Controllers\Controller;
use App\Models\Nomina\Sede;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rule;

class SedeInstitucionalController extends Controller
{
    /**
     * 👁️ Listar sedes por empresa (solo tipo institucional)
     */
    public function index()
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $idEmpresa = $payload->get('idCompany');

        if (!$idEmpresa) {
            return response()->json([
                'success' => false,
                'message' => 'No hay empresa activa seleccionada'
            ], 400);
        }

        $sedes = Sede::with(['ciudad:id,descripcion'])
            ->where('idEmpresa', $idEmpresa)
            ->where('tipo', 'institucional') // ✅ solo instituciones
            ->latest()
            ->get();

        $sedes->transform(function ($sede) {
            if ($sede->ciudad && $sede->ciudad->descripcion) {
                $sede->ciudad->descripcion = ucwords(strtolower($sede->ciudad->descripcion));
            }

            if (!empty($sede->urlImagen)) {
                $path = str_replace('\\', '/', $sede->urlImagen);
                $sede->rutaImagenUrl = url('storage/' . $path);
            } else {
                $sede->rutaImagenUrl = url('storage/default/sede.png');
            }

            return $sede;
        });

        return response()->json([
            'success' => true,
            'data' => $sedes
        ], 200);
    }

    /**
     * ➕ Crear sede (tipo institucional)
     */
    public function store(Request $request)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $idEmpresa = $payload->get('idCompany');

        if (!$idEmpresa) {
            return response()->json([
                'message' => 'No hay empresa activa'
            ], 400);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'idCiudad' => [
                'required',
                Rule::exists((new City)->getTable(), 'id')
            ],
            'imagen' => 'nullable|image|max:2048',
        ]);

        $sede = new Sede();
        $sede->fill([
            'nombre' => $request->nombre,
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'descripcion' => $request->descripcion,
            'idCiudad' => $request->idCiudad,
            'idEmpresa' => $idEmpresa,
            'tipo' => 'institucional', // ✅ asignar tipo automáticamente
        ]);

        if ($request->hasFile('imagen')) {
            $sede->urlImagen = $request->file('imagen')->store('sedes', 'public');
        }

        $sede->save();

        return response()->json([
            'success' => true,
            'message' => 'Sede creada correctamente',
            'data' => $sede->load('ciudad')
        ], 201);
    }

    /**
     * ✏️ Actualizar sede
     */
    public function update(Request $request, $id)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $idEmpresa = $payload->get('idCompany');

        $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:50',
            'descripcion' => 'required|string',
            'idCiudad' => ['required', Rule::exists((new City)->getTable(), 'id')],
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $sede = Sede::where('id', $id)
            ->where('idEmpresa', $idEmpresa)
            ->where('tipo', 'institucional') // ✅ solo instituciones
            ->first();

        if (!$sede) {
            return response()->json([
                'message' => 'Sede no encontrada'
            ], 404);
        }

        DB::transaction(function () use ($request, $sede) {
            if ($request->hasFile('imagen')) {
                if ($sede->urlImagen) {
                    Storage::disk('public')->delete($sede->urlImagen);
                }
                $sede->urlImagen = $request->file('imagen')->store('sedes', 'public');
            }

            $sede->update([
                'nombre' => $request->nombre,
                'direccion' => $request->direccion,
                'telefono' => $request->telefono,
                'descripcion' => $request->descripcion,
                'idCiudad' => $request->idCiudad,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Sede actualizada correctamente',
            'data' => $sede->load('ciudad')
        ]);
    }

    /**
     * 🗑️ Eliminar sede
     */
    public function destroy($id)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $idEmpresa = $payload->get('idCompany');

        $sede = Sede::withCount('infraestructuras')
            ->where('id', $id)
            ->where('idEmpresa', $idEmpresa)
            ->where('tipo', 'institucional') // ✅ solo instituciones
            ->first();

        if (!$sede) {
            return response()->json([
                'message' => 'Sede no encontrada'
            ], 404);
        }

        if ($sede->infraestructuras_count > 0) {
            return response()->json([
                'message' => 'La sede tiene infraestructuras asociadas, elimínelas primero'
            ], 409);
        }

        if ($sede->urlImagen) {
            Storage::disk('public')->delete($sede->urlImagen);
        }

        $sede->delete();

        return response()->json([
            'message' => 'Sede eliminada correctamente'
        ]);
    }

    /**
     * 👁️ Obtener una sede por id
     */
    public function show($id)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $idEmpresa = $payload->get('idCompany');

        $sede = Sede::with([
            'ciudad:id,descripcion,idDepartamento',
            'ciudad.departamento:id,descripcion'
        ])
        ->where('id', $id)
        ->where('idEmpresa', $idEmpresa)
        ->where('tipo', 'institucional') // ✅ solo instituciones
        ->first();

        if (!$sede) {
            return response()->json([
                'message' => 'Sede no encontrada'
            ], 404);
        }

        if ($sede->ciudad) {
            if ($sede->ciudad->descripcion) {
                $sede->ciudad->descripcion = ucwords(strtolower($sede->ciudad->descripcion));
            }
            if ($sede->ciudad->departamento && $sede->ciudad->departamento->descripcion) {
                $sede->ciudad->departamento->descripcion = ucwords(strtolower($sede->ciudad->departamento->descripcion));
            }
        }

        if (!empty($sede->urlImagen)) {
            $path = str_replace('\\', '/', $sede->urlImagen);
            $sede->rutaImagenUrl = url('storage/' . $path);
        } else {
            $sede->rutaImagenUrl = url('storage/default/sede.png');
        }

        return response()->json([
            'success' => true,
            'data' => $sede
        ]);
    }
}
