<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Bonificacion;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BonificacionController extends Controller
{
    public function getBonificaciones()
    {
        $bonificaciones = Bonificacion::with('contrato.persona')->get();
        return response()->json($bonificaciones);
    }

    public function storeBonificacion(Request $request)
    {
    
        $request->validate([
            'idContrato'   => 'required|exists:contrato,id',
            'valor'        => 'required|numeric|min:1',
            'observacion'  => 'required|string',
            'fechaInicial' => 'required|date',
            'fechaFinal'   => 'required|date|after_or_equal:fechaInicial',
            'tipo'         => 'required|in:HABITUAL,TEMPORAL',
            'descripcion'  => 'required|string',
        ]);

        $contrato = Contract::with('salario')->find($request->idContrato);

        if (!$contrato || !$contrato->salario) {
            return response()->json([
                'message' => 'El contrato no tiene un salario asociado'
            ], 400);
        }

      
        $bonificacion = new Bonificacion();
        $bonificacion->idContrato   = $request->idContrato;
        $bonificacion->valor        = $request->valor;
        $bonificacion->observacion  = $request->observacion;
        $bonificacion->fechaInicial = Carbon::parse($request->fechaInicial);
        $bonificacion->fechaFinal   = Carbon::parse($request->fechaFinal);
        $bonificacion->frecuencia         = $request->tipo; 
        $bonificacion->descripcion  = $request->descripcion;
        $bonificacion->estado       = 'APROBADO'; 

        $bonificacion->save();

        return response()->json([
            'message' => 'Bonificación creada exitosamente',
            'data'    => $bonificacion,
        ], 201);
    }

    public function updateBonificacion(Request $request, $id)
    {
        $bonificacion = Bonificacion::find($id);
        if (!$bonificacion) {
            return response()->json(['message' => 'Bonificación no encontrada'], 404);
        }

        $request->validate([
            'valor'        => 'required|numeric|min:1',
            'observacion'  => 'required|string',
            'fechaInicial' => 'required|date',
            'fechaFinal'   => 'required|date|after_or_equal:fechaInicial',
            'tipo'         => 'required|in:HABITUAL,TEMPORAL',
            'descripcion'  => 'required|string',
        ]);

        $bonificacion->valor        = $request->valor;
        $bonificacion->observacion  = $request->observacion;
        $bonificacion->fechaInicial = Carbon::parse($request->fechaInicial);
        $bonificacion->fechaFinal   = Carbon::parse($request->fechaFinal);
        $bonificacion->frecuencia         = $request->tipo;
        $bonificacion->descripcion  = $request->descripcion;
        $bonificacion->save();

        return response()->json([
            'message' => 'Bonificación actualizada exitosamente',
            'data'    => $bonificacion,
        ]);
    }
}
