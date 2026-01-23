<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Models\Nomina\Sede;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SedeController extends Controller
{
    /**
     * 📋 Listar SOLO sedes administrativas
     */
    public function index(): JsonResponse
    {
        $idCompany = KeyUtil::idCompany();

        $sedes = Sede::where('idEmpresa', $idCompany)
            ->where('tipo', 'administrativo') // ✅ filtro clave
            ->get();

        return response()->json($sedes);
    }

    /**
     * 📋 Listar sedes administrativas con responsable
     */
    public function getAllSedes()
    {
        $idCompany = KeyUtil::idCompany();

        $sedes = Sede::with('responsable.persona')
            ->where('idEmpresa', $idCompany)
            ->where('tipo', 'administrativo') // ✅ filtro clave
            ->get();

        return response()->json($sedes);
    }

    /**
     * ➕ Crear sede administrativa
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre'        => 'required|string|max:255',
            'direccion'     => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'telefono'      => 'required|string|max:20',
            'celular'       => 'required|string|max:20',
            'idResponsable' => 'required|integer',
            'descripcion'   => 'nullable|string',
            'imagen'        => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);

        $urlImagen = Sede::RUTA_FOTO_DEFAULT;

        if ($request->hasFile('imagen')) {
            $urlImagen = $request->file('imagen')->store('sedes', 'public');
        }

        $sede = Sede::create([
            'nombre'        => $request->nombre,
            'direccion'     => $request->direccion,
            'email'         => $request->email,
            'telefono'      => $request->telefono,
            'celular'       => $request->celular,
            'descripcion'   => $request->descripcion,
            'idResponsable' => $request->idResponsable,
            'urlImagen'     => $urlImagen,
            'idEmpresa'     => KeyUtil::idCompany(),
            'tipo'          => 'administrativo', // ✅ automático
        ]);

        return response()->json($sede, 201);
    }

    /**
     * 👁️ Ver sede administrativa
     */
    public function show(string $id): JsonResponse
    {
        $sede = Sede::where('id', $id)
            ->where('tipo', 'administrativo') // ✅ seguridad extra
            ->firstOrFail();

        return response()->json($sede);
    }

    /**
     * ✏️ Actualizar sede administrativa
     */
    public function updateSede(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre'        => 'required|string|max:255',
            'direccion'     => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'telefono'      => 'required|string|max:20',
            'celular'       => 'required|string|max:20',
            'idResponsable' => 'required|integer',
            'descripcion'   => 'nullable|string',
            'imagen'        => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);

        $sede = Sede::where('id', $id)
            ->where('tipo', 'administrativo')
            ->firstOrFail();

        $dataToUpdate = [
            'nombre'        => $request->nombre,
            'direccion'     => $request->direccion,
            'email'         => $request->email,
            'telefono'      => $request->telefono,
            'celular'       => $request->celular,
            'idResponsable' => $request->idResponsable,
            'descripcion'   => $request->descripcion,
        ];

        if ($request->hasFile('imagen')) {
            if ($sede->urlImagen && $sede->urlImagen !== Sede::RUTA_FOTO_DEFAULT) {
                Storage::disk('public')->delete($sede->urlImagen);
            }

            $dataToUpdate['urlImagen'] = $request->file('imagen')
                ->store('sedes', 'public');
        }

        $sede->update($dataToUpdate);

        return response()->json([
            'success' => true,
            'message' => 'Sede administrativa actualizada correctamente',
            'data'    => $sede
        ]);
    }

    /**
     * 🗑️ Eliminar sede administrativa
     */
    public function destroy(string $id): JsonResponse
    {
        $sede = Sede::where('id', $id)
            ->where('tipo', 'administrativo')
            ->firstOrFail();

        if ($sede->urlImagen && $sede->urlImagen !== Sede::RUTA_FOTO_DEFAULT) {
            Storage::disk('public')->delete($sede->urlImagen);
        }

        $sede->delete();

        return response()->json(null, 204);
    }
}
