<?php

namespace App\Http\Controllers;

use App\Models\Lugar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LugarController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        return response()->json(Lugar::orderBy('nombre')->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $place = Lugar::create($request->all());
        return response()->json($place->load('ciudad'), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Lugar  $lugar
     * @return \Illuminate\Http\Response
     */
    public function show(string|int $id): JsonResponse
    {
        $place = Lugar::findOrFail($id);
        return response()->json($place);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Lugar  $lugar
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, string|int $id): JsonResponse
    {
        $place = Lugar::findOrFail($id);
        $place->update($request->all());
        return response()->json($place->load('ciudad'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Lugar  $lugar
     * @return \Illuminate\Http\Response
     */
    public function destroy(string|int $id): JsonResponse
    {
        $place = Lugar::findOrFail($id);
        $place->delete();
        return response()->json(null, 204);
    }
}
