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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $idCompany = KeyUtil::idCompany();
        $sedes = Sede::where('idEmpresa', $idCompany)->get();
        return response()->json($sedes);
    }

    public function getAllSedes()   {
        $idCompany = KeyUtil::idCompany();
        $sedes = Sede::with('responsable.persona')
            ->where('idEmpresa', $idCompany)
            ->get();
    
        return response()->json($sedes);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $urlImagen = null;
        if ($request->hasFile('imagen')) {
            $urlImagen = $request
                ->file('imagen')
                ->store('sedes', ['disk' => 'public']);
        }
        $request->request->add(['urlImagen' => $urlImagen ?? Sede::RUTA_FOTO_DEFAULT]);
        $request->request->add(['idEmpresa' => KeyUtil::idCompany()]);
        $sede = Sede::create($request->all());
        return response()->json($sede, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Sede  $sede
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $sede = Sede::findOrFail($id);
        return response()->json($sede);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Sede  $sede
     * @return \Illuminate\Http\Response
     */
    public function updateSede(Request $request, $id)
    {
        $request->validate([
            'nombre'       => 'required|string|max:255',
            'direccion'    => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'telefono'     => 'required|string|max:20',
            'celular'      => 'required|string|max:20',
            'idResponsable' => 'required|integer',
            'descripcion'  => 'nullable|string',
            'imagen'       => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);

        $sede = Sede::findOrFail($id);

        $dataToUpdate = [
            'nombre'       => $request->input('nombre'),
            'direccion'    => $request->input('direccion'),
            'email'        => $request->input('email'),
            'telefono'     => $request->input('telefono'),
            'celular'      => $request->input('celular'),
            'idResponsable' => $request->input('idResponsable'),
            'descripcion'  => $request->input('descripcion'),
        ];


        if ($request->hasFile('imagen')) {
            if ($sede->urlImagen && $sede->urlImagen !== Sede::RUTA_FOTO_DEFAULT) {
                Storage::disk('public')->delete($sede->urlImagen);
            }

            $path = $request->file('imagen')->store('sedes', 'public');
            $dataToUpdate['urlImagen'] = $path;
        }

        $sede->update($dataToUpdate);

        return response()->json([
            'success' => 'Sede actualizada con éxito',
            'sede'    => $sede
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Sede  $sede
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $sede = Sede::findOrFail($id);
        Storage::disk('public')->delete($sede->urlImagen);
        $sede->delete();
        return response()->json(null, 204);
    }
}
