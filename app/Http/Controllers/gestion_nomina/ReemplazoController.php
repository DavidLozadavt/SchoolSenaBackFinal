<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Reemplazo;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReemplazoController extends Controller
{
    public function getReemplazos()
    {
        $reemplazos = Reemplazo::with(['contratoReemplazo.persona', 'contratoTrabajador.persona', 'cargo', 'pago'])
            ->where('estado', 'ACTIVO')
            ->get();

        return response()->json($reemplazos);
    }

    public function getReemplazosFinalizados()
    {
        $reemplazos = Reemplazo::with(['contratoReemplazo.persona', 'contratoTrabajador.persona', 'cargo', 'pago'])
            ->where('estado', 'FINALIZADO')
            ->get();

        return response()->json($reemplazos);
    }



    public function storeReemplazo(Request $request)
    {
        $request->validate([
            'idContratoRemplazo'   => 'required|exists:contrato,id',
            'idContratoTrabajador' => 'required|exists:contrato,id',
            'exedente'             => 'nullable|numeric|min:0',
            'observacion'          => 'required|string',
            'fechaInicial'         => 'required|date',
            'fechaFinal'           => 'required|date|after_or_equal:fechaInicial',
        ]);


        $contratoTrabajador = Contract::with('salario')->find($request->idContratoTrabajador);
        $contratoReemplazo = Contract::with('salario')->find($request->idContratoRemplazo);

        $reemplazo = new Reemplazo();
        $reemplazo->idContratoRemplazo    = $request->idContratoRemplazo;
        $reemplazo->idContratoTrabajador  = $request->idContratoTrabajador;
        $reemplazo->idCargo               = $contratoTrabajador->salario?->rol_id;
        $reemplazo->exedente              = $request->exedente ?? 0;
        $reemplazo->salarioCargoTrabajador = $contratoTrabajador->salario?->valor;
        $reemplazo->salarioCargoReemplazo = $contratoReemplazo->salario?->valor;
        $reemplazo->observacion           = $request->observacion;
        $reemplazo->fechaInicial          = Carbon::parse($request->fechaInicial);
        $reemplazo->fechaFinal            = Carbon::parse($request->fechaFinal);
        $reemplazo->estado                = 'ACTIVO';
        $reemplazo->save();

        return response()->json([
            'message' => 'Reemplazo creado exitosamente',
            'data'    => $reemplazo,
        ], 201);
    }

    public function updateReemplazo(Request $request, $id)
    {
        $reemplazo = Reemplazo::find($id);
        if (!$reemplazo) {
            return response()->json(['message' => 'Reemplazo no encontrado'], 404);
        }

        $request->validate([
            'idContratoRemplazo'   => 'required|exists:contrato,id',
            'idContratoTrabajador' => 'required|exists:contrato,id',
            'exedente'             => 'nullable|numeric|min:0',
            'observacion'          => 'required|string',
            'fechaInicial'         => 'required|date',
            'fechaFinal'           => 'required|date|after_or_equal:fechaInicial',
            'estado'               => 'nullable|in:ACTIVO,FINALIZADO',
        ]);

        $contratoTrabajador = Contract::with('salario')->find($request->idContratoTrabajador);
        $contratoReemplazo = Contract::with('salario')->find($request->idContratoRemplazo);



        $reemplazo->idContratoRemplazo    = $request->idContratoRemplazo;
        $reemplazo->idContratoTrabajador  = $request->idContratoTrabajador;
        $reemplazo->idCargo               = $contratoTrabajador->salario?->rol_id;
        $reemplazo->exedente              = $request->exedente;
        $reemplazo->salarioCargoTrabajador = $contratoTrabajador->salario?->valor;
        $reemplazo->salarioCargoReemplazo = $contratoReemplazo->salario?->valor;
        $reemplazo->observacion           = $request->observacion;
        $reemplazo->fechaInicial          = Carbon::parse($request->fechaInicial);
        $reemplazo->fechaFinal            = Carbon::parse($request->fechaFinal);

        if ($request->filled('estado')) {
            $reemplazo->estado = $request->estado;
        }

        $reemplazo->save();

        return response()->json([
            'message' => 'Reemplazo actualizado exitosamente',
            'data'    => $reemplazo,
        ]);
    }



    public function destroyReemplazo($id)
    {
        $reemplazo = Reemplazo::find($id);

        if (!$reemplazo) {
            return response()->json(['message' => 'Reemplazo no encontrado'], 404);
        }

        $reemplazo->delete();

        return response()->json([
            'message' => 'Reemplazo eliminado exitosamente'
        ], 200);
    }
}
