<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\ConfiguracionNomina;
use App\Models\Nomina\HistorialConfiguracionNomina;
use App\Models\Salario;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionNominaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $configuracionNomina = ConfiguracionNomina::where('idEmpresa', KeyUtil::idCompany())->first();
        return response()->json($configuracionNomina);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $request->request->add(['idEmpresa' => KeyUtil::idCompany()]);
        $configuracionNomina = ConfiguracionNomina::create($request->all());
        return response()->json($configuracionNomina, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ConfiguracionNomina  $configuracionNomina
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $configuracionNomina = ConfiguracionNomina::findOrFail($id);
        return response()->json($configuracionNomina);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ConfiguracionNomina  $configuracionNomina
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $configuracionNomina = ConfiguracionNomina::findOrFail($id);
        HistorialConfiguracionNomina::create($configuracionNomina->toArray());
        $configuracionNomina->update($request->all());
        return response()->json($configuracionNomina, 200);
    }

    /**
     * Update annual increment smlv
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return JsonResponse|mixed
     */
    public function updateAnnualIncrementSMLV(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'incrementoAnualSMLV' => 'required|numeric',
        ]);

        $payrollConfiguration = ConfiguracionNomina::findOrFail($id);
        $incrementoAnualSMLV = $request->incrementoAnualSMLV;

        $payrollConfiguration->update([
            'incrementoAnualSMLV' => $incrementoAnualSMLV,
        ]);

        try {
            Salario::query()->update(['valor' => DB::raw("valor + valor * ($incrementoAnualSMLV / 100)")]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar los salarios'], 500);
        }

        return response()->json(['message' => 'Incremento anual actualizado correctamente.']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ConfiguracionNomina  $configuracionNomina
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $configuracionNomina = ConfiguracionNomina::findOrFail($id);
        $configuracionNomina->delete();
        return response()->json(null, 204);
    }
}
