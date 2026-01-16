<?php

namespace App\Http\Controllers\gestion_productos;

use App\Http\Controllers\Controller;
use App\Models\Medida;
use Illuminate\Http\Request;

class MedidasProController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $medidas = Medida::inRandomOrder()->take(10)->get();
        return response()->json($medidas);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {

            // Validaciones simples
            $request->validate([
                'valor' => 'required',
                'unidadMedida' => 'required'
            ]);

            // Verificar si ya existe en la BD
            $medida = Medida::where('valor', $request->valor)
                ->where('unidadMedida', $request->unidadMedida)
                ->first();

            // Si ya existe la devolvemos
            if ($medida) {
                return response()->json($medida, 200);
            }

            // Crear nueva medida
            $medida = new Medida();
            $medida->valor = $request->valor;
            $medida->unidadMedida = $request->unidadMedida;
            $medida->save();

            return response()->json($medida, 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }

    public function show($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }

    /**
     * Si ya NO vas a usar relaciones, este método no tiene sentido.
     * Lo dejo vacío por si lo necesitas después.
     */
    public function getMedidas($id)
    {
        return response()->json([], 200);
    }
}
