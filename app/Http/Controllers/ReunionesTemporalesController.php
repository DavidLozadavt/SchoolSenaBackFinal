<?php

namespace App\Http\Controllers;

use App\Models\ReunionesTemporales;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReunionesTemporalesController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|unique:reuniones_temporales',
            'duracion_minutos' => 'required|integer|min:5|max:480',
            'nombre' => 'string|max:255',
            'start_at' => 'nullable|date',             
        ]);

        $startAt = isset($validated['start_at'])
            ? Carbon::parse($validated['start_at'])     
            : now();                                      

        $reunion = ReunionesTemporales::create([
            'codigo' => $validated['codigo'],
            'room_name' => 'meet-' . strtolower(str_replace('-', '', $validated['codigo'])),
            'created_by' => auth()->id(),
            'nombre' => $validated['nombre'] ?? null,
            'start_at' => $validated['start_at'] ?? null,
            'expires_at' => $startAt->copy()->addMinutes($validated['duracion_minutos']),
        ]);

        return response()->json($reunion, 201);
    }

    public function index()
    {
        $reuniones = ReunionesTemporales::all();
            

        return response()->json($reuniones);
    }

    public function update(Request $request, ReunionesTemporales $reunion)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255'
        ]);

        $reunion->update($validated);
        return response()->json($reunion);
    }

    public function extend(Request $request, ReunionesTemporales $reunion)
    {
        $validated = $request->validate([
            'minutos' => 'required|integer|min:5|max:480'
        ]);

        $reunion->update([
            'expires_at' => $reunion->expires_at->addMinutes($validated['minutos'])
        ]);

        return response()->json($reunion);
    }

    public function destroy($id)
    {
        $reunion = ReunionesTemporales::find($id);
        $reunion->delete();
        return response()->json(null, 204);
    }
}
