<?php

namespace App\Http\Controllers\gestion_nomina;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Nomina\CentroCosto;
use App\Http\Controllers\Controller;
use App\Models\CentroOperacion;
use App\Util\KeyUtil;
use Carbon\Carbon;

class CentroCostoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $costCenters = CentroCosto::with(['sede', 'area'])->when($request->idSede, function ($query) use ($request) {
            $query->where('idSede', $request->idSede);
        })
            ->when($request->idArea, function ($query) use ($request) {
                $query->where('idArea', $request->idArea);
            }, function ($query) {
                $query->whereHas('sede', function ($query) {
                    $query->where('idEmpresa', KeyUtil::idCompany());
                });
            })
            ->get();

        return response()->json($costCenters);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $request->request->add(['año' => Carbon::now()->year]);
        $centerCost = CentroCosto::create(attributes: $request->all());
        return response()->json($centerCost, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CentroCosto  $centroCosto
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        return response()->json(CentroCosto::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CentroCosto  $centroCosto
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $centerCost = CentroCosto::findOrFail($id);
        $centerCost->update($request->all());
        return response()->json($centerCost, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CentroCosto  $centroCosto
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $centerCost = CentroCosto::findOrFail($id);
        $centerCost->delete();
        return response()->json(null, 204);
    }


    public function getCentroOperaciones()
    {

        $centros = CentroOperacion::all();

        return response()->json($centros, 200);
    }


    public function storeCentroOperacion(Request $request)
    {

        $centros = CentroOperacion::create($request->all());
        return response()->json($centros, 201);
    }
}
