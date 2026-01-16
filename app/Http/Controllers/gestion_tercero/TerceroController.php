<?php

namespace App\Http\Controllers\gestion_tercero;

use App\Http\Controllers\Controller;
use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TerceroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $identificacion = $request->query('identificacion');
        $companyId = KeyUtil::idCompany();

        $query = Tercero::where('idCompany', $companyId);

        if ($identificacion) {
            if (strlen($identificacion) < 5) {
                $query->where('identificacion', 'like', '%' . $identificacion . '%');
            } else {
                $query->where('identificacion', $identificacion);
            }
        }

        $terceros = $query->limit(10)->get();

        return response()->json($terceros);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $tercero = new Tercero();
            $tercero->nombre = $request->input('nombre');
            $tercero->identificacion = $request->input('identificacion');
            $tercero->email = $request->input('email');
            $tercero->direccion = $request->input('direccion');
            $tercero->telefono = $request->input('telefono');
            $tercero->digitoVerficacion = $request->input('digitoVerficacion');
            $tercero->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $tercero->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');
            $tercero->idTipoIdentificacion = $request->input('tipoIdentificacion', 1);
            $tercero->idCompany = KeyUtil::idCompany();
            $tercero->idTipoTercero = TipoTercero::PROVEEDOR;
            $tercero->save();

            return response()->json($tercero, 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $tercero = Tercero::findOrFail($id);
        return response()->json($tercero);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {

            $tercero = Tercero::findOrFail($id);

            $tercero->nombre = $request->input('nombre', $tercero->nombre);
            $tercero->identificacion = $request->input('nit', $tercero->identificacion);
            $tercero->email = $request->input('email', $tercero->email);
            $tercero->direccion = $request->input('direccion', $tercero->direccion);
            $tercero->telefono = $request->input('telefono', $tercero->telefono);
            $tercero->digitoVerficacion = $request->input('digitoVerficacion', $tercero->digitoVerficacion);
            $tercero->responsableIva = $request->input('responsableIva', $tercero->responsableIva);
            $tercero->retenciones = $request->input('retenciones', $tercero->retenciones);

            $tercero->save();

            return response()->json($tercero, 200);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $tercero = Tercero::findOrFail($id);

        if ($tercero->facturas()->exists()) {
            return response()->json(['error' => 'No se puede eliminar, tiene facturas asociadas.'], 400);
        }

        $tercero->delete();
        return response()->json([], 204);
    }


    public function buscarTercero(Request $request)
    {
        $idCompany = KeyUtil::idCompany();
        if (!$idCompany) {
            return response()->json(['message' => 'Compañía no encontrada en la sesión'], 400);
        }

        $telefono = $request->input('telefono');
        $identificacion = $request->input('identificacion');

        if (!$telefono && !$identificacion) {
            return response()->json(['message' => 'Debe proporcionar teléfono o identificación'], 400);
        }

        $query = Tercero::where('idCompany', $idCompany);

        if ($telefono) {
            $query->where('telefono', $telefono);
        }

        if ($identificacion) {
            $query->orWhere('identificacion', $identificacion);
        }

        $tercero = $query->first();

        if ($tercero) {
            return response()->json($tercero, 200);
        } else {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }
    }



    public function getSocios(Request $request)
    {
        $identificacion = $request->query('identificacion');

        $query = Tercero::where('idCompany', KeyUtil::idCompany())
            ->where('idTipoTercero', TipoTercero::SOCIO);

        if ($identificacion) {
            $query->where('identificacion', 'like', '%' . $identificacion . '%');
        }

        $terceros = $query->get();
        return response()->json($terceros);
    }






    public function storeSocio(Request $request)
    {
        try {
            $socio = new Tercero();
            $socio->nombre = $request->input('nombre');
            $socio->identificacion = $request->input('nit');
            $socio->email = $request->input('email');
            $socio->direccion = $request->input('direccion');
            $socio->telefono = $request->input('telefono');
            $socio->digitoVerficacion = $request->input('digitoVerficacion');
            $socio->idTipoIdentificacion = $request->input('tipoIdentificacion', 1);
            $socio->idCompany = KeyUtil::idCompany();
            $socio->idTipoTercero = TipoTercero::SOCIO;
            $socio->save();

            return response()->json($socio, 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }
}
