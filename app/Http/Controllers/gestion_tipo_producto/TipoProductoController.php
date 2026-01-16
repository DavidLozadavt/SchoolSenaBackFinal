<?php

namespace App\Http\Controllers\gestion_tipo_producto;

use App\Http\Controllers\Controller;
use App\Models\SubCuentaPropia;
use App\Models\TipoProducto;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class TipoProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tipoProductos = TipoProducto::all();

        return response()->json($tipoProductos);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $subcuenta_id = $request->input('subcuenta_id');
            $nombreSubcuentaPropia = $request->input('nombreSubcuentaPropia');
            $codigoBase = $request->input('codigo');
    
            $maximoActual = SubCuentaPropia::where('subcuenta_id', $subcuenta_id)->max('codigo');
    
            if ($maximoActual) {
                $siguienteNumero = (int) substr($maximoActual, -2) + 1;
            } else {
                $siguienteNumero = 1;
            }
    
            $codigoConcatenado = $codigoBase . str_pad($siguienteNumero, 2, '0', STR_PAD_LEFT);
    
            $subCuenta = new SubCuentaPropia();
            $subCuenta->subcuenta_id = $subcuenta_id;
            $subCuenta->nombreSubcuentaPropia = $nombreSubcuentaPropia;
            $subCuenta->codigo = $codigoConcatenado;
            
            $subCuenta->save();

            $tipoProducto = new TipoProducto();
            $tipoProducto->nombreTipoProducto = $request->input('nombreTipoProducto');
            $tipoProducto->descripcion = $request->input('descripcion');
            $tipoProducto->idClaseCuenta = $request->input('idClaseCuenta');
            $tipoProducto->idClaseProducto = $request->input('idClaseProducto');
            $tipoProducto->idSubcuentaPropia = $subCuenta->id;
            $tipoProducto->idCompany = KeyUtil::idCompany();

            $tipoProducto->save();

            DB::commit();

            return response()->json(['tipoProducto' => $tipoProducto, 'subCuenta' => $subCuenta], 201);
      
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
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
        //
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

    public function getTipoProductos($id)
    {
        $tipoProductos = TipoProducto::where('idClaseProducto', $id)->get();

        return response()->json($tipoProductos);
    }
}
