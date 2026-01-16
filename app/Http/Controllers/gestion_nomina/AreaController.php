<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Models\Nomina\Area;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $areas = Area::when($request->idSede, function ($query) use ($request) {
            $query->where('idSede', $request->idSede);
        }, function ($query) {
            $query->whereHas('sede', function ($query) {
                $query->where('idEmpresa', KeyUtil::idCompany());
            });
        })->get();

        return response()->json($areas);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $area = Area::create($request->all());
        return response()->json($area, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function show(string $id): JsonResponse
    {
        $area = Area::findOrFail($id);
        return response()->json($area);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $area = Area::findOrFail($id);
        $area->update($request->all());
        return response()->json($area, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $id): JsonResponse
    {
        $area = Area::findOrFail($id);
        $area->delete();
        return response()->json(null, 204);
    }


    public function getAllAreas()
    {
        $areas = Area::with('sede')->get();
        return response()->json($areas);
    }
}
