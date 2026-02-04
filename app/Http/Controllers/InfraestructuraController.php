<?php

namespace App\Http\Controllers;

use App\Models\Infraestructura;
use App\Models\TipoInfraestructura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InfraestructuraController extends Controller
{
    // Listar todas las infraestructuras de una sede
    public function index($idSede)
    {
        $infraestructuras = Infraestructura::with('tipoInfraestructura')
            ->where('idSede', $idSede)
            ->get();

        return response()->json(['data' => $infraestructuras]);
    }

    // Mostrar una infraestructura específica
    public function show($id)
    {
        $infraestructura = Infraestructura::with('tipoInfraestructura')->find($id);

        if (!$infraestructura) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        return response()->json(['data' => $infraestructura]);
    }

    // Crear infraestructura
    public function store(Request $request)
    {
        $request->validate([
            'nombreInfraestructura' => 'required|string|max:255',
            'capacidad' => 'required|integer|min:1',
            'idSede' => 'required|exists:sedes,id',
            'idTipoInfraestructura' => 'required|exists:tiposinfraestructura,id',
        ]);

        $infraestructura = Infraestructura::create([
            'nombreInfraestructura' => $request->nombreInfraestructura,
            'capacidad' => $request->capacidad,
            'idSede' => $request->idSede,
            'idTipoInfraestructura' => $request->idTipoInfraestructura,
        ]);

        return response()->json([
            'message' => 'Infraestructura creada correctamente',
            'data' => $infraestructura->load('tipoInfraestructura')
        ], 201);
    }

    // Actualizar infraestructura
    public function update(Request $request, $id)
    {
        $infraestructura = Infraestructura::find($id);

        if (!$infraestructura) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $request->validate([
            'nombreInfraestructura' => 'required|string|max:255',
            'capacidad' => 'required|integer|min:1',
            'idTipoInfraestructura' => 'required|exists:tiposinfraestructura,id',
        ]);

        $infraestructura->update([
            'nombreInfraestructura' => $request->nombreInfraestructura,
            'capacidad' => $request->capacidad,
            'idTipoInfraestructura' => $request->idTipoInfraestructura,
        ]);

        return response()->json([
            'message' => 'Infraestructura actualizada correctamente',
            'data' => $infraestructura->load('tipoInfraestructura')
        ]);
    }

    // Eliminar infraestructura
    public function destroy($id)
    {
        $infraestructura = Infraestructura::find($id);

        if (!$infraestructura) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $infraestructura->delete();

        return response()->json(['message' => 'Infraestructura eliminada correctamente']);
    }

    // Listar todos los tipos de infraestructura (para selects)
    public function tipos()
    {
        try {
            // Verificar que el modelo use la tabla correcta
            $model = new TipoInfraestructura();
            $tableName = $model->getTable();
            
            // Si por alguna razón no está usando la tabla correcta, forzar
            if ($tableName !== 'tiposinfraestructura') {
                Log::warning("TipoInfraestructura está usando tabla incorrecta: {$tableName}, forzando 'tiposinfraestructura'");
                $tipos = DB::table('tiposinfraestructura')->orderBy('nombre')->get();
            } else {
                $tipos = TipoInfraestructura::orderBy('nombre')->get();
            }

            return response()->json(['data' => $tipos]);
        } catch (\Exception $e) {
            Log::error('Error en tipos(): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error al obtener tipos de infraestructura'], 500);
        }
    }
    public function infraestructurasPorRegional($idRegional)
{
    $infraestructuras = Infraestructura::with(['tipoInfraestructura', 'sede'])
        ->whereHas('sede', function ($query) use ($idRegional) {
            $query->where('idEmpresa', $idRegional);
        })
        ->get();

    return response()->json(['data' => $infraestructuras]);
}

}
