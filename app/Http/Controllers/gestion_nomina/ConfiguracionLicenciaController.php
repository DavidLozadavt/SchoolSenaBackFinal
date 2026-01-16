<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionIncapacidadLicencia;
use Illuminate\Http\Request;

class ConfiguracionLicenciaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $configuracion = ConfiguracionIncapacidadLicencia::first();

        return response()->json($configuracion, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $configuracion = ConfiguracionIncapacidadLicencia::create($request->all());
        return response()->json($configuracion, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
        $configuracion = ConfiguracionIncapacidadLicencia::findOrFail($id);

        $configuracion->update($request->all());
        return response()->json($configuracion, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
