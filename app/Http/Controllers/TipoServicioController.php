<?php

namespace App\Http\Controllers;

use App\Models\ClaseServicio;
use App\Models\Servicio;
use App\Models\SubCuentaPropia;
use App\Models\TipoServicio;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipoServicioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tipoServicio = TipoServicio::all();
        return response()->json($tipoServicio);
    }

    public function getTypeServicesRequest(): JsonResponse
    {
        $servicesRequest = Servicio::whereNotNull('idCategoriaServicio')
            ->get();
    
        return response()->json($servicesRequest);
    }
    

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $tipoServicio = new TipoServicio($data);
        $tipoServicio->save();
        return response()->json($tipoServicio, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->all();
        $tipoServicio = TipoServicio::findOrFail($id);
        $tipoServicio->fill($data);

        $tipoServicio->save();

        return response()->json($tipoServicio);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $tipoServicio = TipoServicio::findOrFail($id);
        $tipoServicio->delete();

        return response()->json([], 204);
    }


    public function getClaseServices(){
        $idCompany =  KeyUtil::idCompany();
    
        $claseServicios = ClaseServicio::where('idCompany', $idCompany)->get();
    
        return response()->json($claseServicios);
    }


    public function getTipoServicios($id){
        
        $tipoServicios = TipoServicio::where('idClaseServicio', $id)->get();

        return response()->json($tipoServicios);
    }



    public function storeClaseServicio(Request $request)
    {
        DB::beginTransaction();
    
        try {
            $idCompany = KeyUtil::idCompany();
            $subcuenta_id = $request->input('subcuenta_id');
            $subcuentas = $request->input('subcuentas');
            $cuenta_id = $request->input('cuentas');
            $nombreSubcuentaPropia = $request->input('nombreSubcuentaPropia');
            $codigoBase = $request->input('codigo');
    
      
            $codigosExistentes = SubCuentaPropia::where('codigo', 'like', "{$codigoBase}%")
                ->where('idCompany', $idCompany) 
                ->pluck('codigo')
                ->map(function ($codigo) use ($codigoBase) {
                    return (int) substr($codigo, strlen($codigoBase)); 
                })
                ->toArray();

            $siguienteNumero = empty($codigosExistentes) ? 1 : (max($codigosExistentes) + 1);

            $codigoConcatenado = $codigoBase . str_pad($siguienteNumero, 2, '0', STR_PAD_LEFT);
    
            $subCuenta = new SubCuentaPropia();
            $subCuenta->subcuenta_id = $subcuentas === null ? null : $subcuenta_id;
            $subCuenta->cuenta_id = $cuenta_id;
            $subCuenta->idCompany = $idCompany; 
            $subCuenta->nombreSubcuentaPropia = $nombreSubcuentaPropia;
            $subCuenta->codigo = $codigoConcatenado;
            $subCuenta->save();
    
    
            $claseServicio = new ClaseServicio();
            $claseServicio->nombreClaseServicio = $request->input('nombreClaseServicio');
            $claseServicio->descripcion = $request->input('descripcion');
            $claseServicio->idCompany = $idCompany;
            $claseServicio->idClaseCuenta = $request->input('idClaseCuenta');
            $claseServicio->idSubcuentaPropia = $subCuenta->id;
            $claseServicio->save();
    
            DB::commit();
    
            return response()->json($claseServicio, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }
    
    
    
}
