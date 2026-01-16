<?php

namespace App\Http\Controllers\gestion_transporte;

use App\Enums\EstadosViaje;
use App\Models\Transporte\Viaje;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Transporte\AgendarViaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;


class AgendarViajeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $scheduling = AgendarViaje::orderBy('id', 'desc')
            ->get();
        return response()->json($scheduling);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $result = $this->validateScheduling($request);
        if ($result['exists']) {
            return response()->json([
                'message' => 'El agendamiento ya existe.',
                'scheduling' => $result['scheduling'],
            ], 422);
        }

        $scheduling = AgendarViaje::create($request->all());
        return response()->json($scheduling, 201);
    }

    public function saveTripAgenda(Request $request): JsonResponse
{
    $result = $this->validateScheduling($request);

    if ($result['exists']) {
        return response()->json([
            'message' => 'El agendamiento ya existe.',
            'scheduling' => $result['scheduling'],
        ], 422);
    }

    try {
        $agendamiento = DB::transaction(function () use ($request) {
            $ultimo = Viaje::lockForUpdate()->max('numeroPlanillaViaje');
            $siguiente = $ultimo ? (int)$ultimo + 1 : 1;

            $numeroPlanillaViaje = str_pad($siguiente, 7, '0', STR_PAD_LEFT);

            $viaje = Viaje::create([
                'numeroPlanillaViaje' => $numeroPlanillaViaje,
                'idRuta' => $request->input('idRuta'),
                'estado' => EstadosViaje::PENDIENTE,
            ]);

            $agendamiento = AgendarViaje::create([
                'idViaje' => $viaje->id,
                'dia' => $request->input('dia'),
                'fecha' => $request->input('fecha'),
                'hora' => $request->input('hora'),
                'descripcion' => $request->input('descripcion'),
            ]);

            $agendamiento->viaje = $viaje;

            return $agendamiento;
        });

        return response()->json($agendamiento, 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al crear el viaje y agendamiento',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Validate scheduling to trip
     * @param \Illuminate\Http\Request $request
     * @return array{exists: bool, scheduling: AgendarViaje|object|\Illuminate\Database\Eloquent\Model|null}
     */
    private function validateScheduling(Request $request, $id = null): array
    {
        $query = AgendarViaje::where('dia', $request->dia)
            ->where('fecha', $request->fecha)
            ->where('hora', $request->hora);

        if ($id) {
            $query->where('id', '<>', $id);
        }

        $scheduling = $query->first();

        return [
            'exists' => (bool) $scheduling,
            'scheduling' => $scheduling
        ];
    }


    /**
     * Display the specified resource.
     *
     * @param  AgendarViaje  $agendarViaje
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $scheduling = AgendarViaje::findOrFail($id);
        return response()->json($scheduling);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  AgendarViaje  $agendarViaje
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $scheduling = AgendarViaje::findOrFail($id);

        $result = $this->validateScheduling($request, $scheduling->id);
        if ($result['exists']) {
            return response()->json([
                'message' => 'El agendamiento ya existe.',
                'scheduling' => $result['scheduling'],
            ], 422);
        }

        $scheduling->update($request->all());
        return response()->json($scheduling);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  AgendarViaje  $agendarViaje
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $scheduling = AgendarViaje::findOrFail($id);
        $scheduling->delete();
        return response()->json(null, 204);
    }
}
