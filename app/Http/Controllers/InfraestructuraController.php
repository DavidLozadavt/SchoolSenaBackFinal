<?php

namespace App\Http\Controllers;

use App\Models\Infraestructura;
use App\Models\TipoInfraestructura;
use Illuminate\Http\Request;

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
        $tipos = TipoInfraestructura::orderBy('nombre')->get();

        return response()->json(['data' => $tipos]);
    }
}
