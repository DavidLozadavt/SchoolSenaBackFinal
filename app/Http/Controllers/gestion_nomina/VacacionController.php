<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Nomina\Vacacion;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $periodo     = $request->periodo;
        $idSolicitud = $request->idSolicitud;
        $idContrato  = $request->idContrato;
        $estado      = $request->estado;

        $user = KeyUtil::user();

        $contract = Contract::where('idpersona', $user->idpersona)
            ->where('idEstado', 1)
            ->latest()
            ->first();

        $vacacions = Vacacion::filterAdvanceVacacion(
            $periodo,
            $idSolicitud,
            $idContrato ?? $contract->id,
            $estado,
        )
            ->orderBy('id', 'desc')
            ->paginate(25);

        return response()->json([
            'total'      => $vacacions->total(),
            'vacaciones' => $vacacions->items()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $vacacion = Vacacion::create($request->all());
        return response()->json($vacacion, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Vacacion  $vacacion
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $vacacion = Vacacion::findOrFail($id);
        return response()->json($vacacion);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Vacacion  $vacacion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $vacacion = Vacacion::findOrFail($id);
        $vacacion->update($request->all());
        return response()->json($vacacion, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Vacacion  $vacacion
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $vacacion = Vacacion::findOrFail($id);
        $vacacion->delete();
        return response()->json(null, 204);
    }
}
