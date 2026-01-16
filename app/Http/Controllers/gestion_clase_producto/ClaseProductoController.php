<?php

namespace App\Http\Controllers\gestion_clase_producto;

use App\Http\Controllers\Controller;
use App\Models\ClaseProducto;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ClaseProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $idCompany = KeyUtil::idCompany();
    
        $claseProductos = ClaseProducto::where('idCompany', $idCompany)->get();
    
        return response()->json($claseProductos);
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
            $claseProducto = new ClaseProducto();
            $claseProducto->nombreClaseProducto = $request->input('nombreClaseProducto');
            $claseProducto->descripcion = $request->input('descripcion');
            $claseProducto->idCompany = KeyUtil::idCompany();
        
            $claseProducto->save();

            DB::commit();

            return response()->json($claseProducto, 201);
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
}
