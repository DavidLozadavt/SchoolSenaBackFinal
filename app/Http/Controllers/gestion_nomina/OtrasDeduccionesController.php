<?php

namespace App\Http\Controllers\gestion_nomina;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Nomina\ConfiguracionNomina;
use App\Models\OtraDeduccion;
use App\Models\TipoConcepto;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OtrasDeduccionesController extends Controller
{

    public function getTipoConceptos()
    {
        $tipoConceptos = TipoConcepto::all();

        return response()->json($tipoConceptos);
    }


    public function storeTipoConcepto(Request $request)
    {
        $data = $request->all();
        $tipoConceptos = new TipoConcepto($data);
        $tipoConceptos->save();

        return response()->json($tipoConceptos, 201);
    }


    public function getDeducciones()
    {
        $deducciones = OtraDeduccion::with('contrato.persona', 'tipoConcepto')->get();

        return response()->json($deducciones);
    }


    public function getDeduccionesUserContrato()
    {
        $contrato = KeyUtil::lastContractActive();

        if (!$contrato) {
            return response()->json([
                'message' => 'No se encontró un contrato activo para esta persona.'
            ], 404);
        }

        $deducciones = OtraDeduccion::with(['tipoConcepto'])
            ->where('idContrato', $contrato->id)
            ->get()
            ->map(function ($deduccion) {
                return [
                    'id' => $deduccion->id,
                    'nombre_deduccion' => $deduccion->tipoConcepto->nombre ?? '',
                    'observacion' => $deduccion->observacion,
                    'valor_cuota' => $deduccion->valor,
                    'valor_total' => $deduccion->valorFinal,
                    'fecha_inicio' => $deduccion->fechaInicio,
                    'fecha_fin' => $deduccion->fechaFin,
                    'estado' => $deduccion->estado,
                    'valor_parcial_pagado' => $deduccion->coutasPagadas ?? 0,
                    'archivo_url' => $deduccion->archivoUrl,
                ];
            });

        return response()->json($deducciones);
    }




    public function storeDeducciones(Request $request)
    {
        if (!$request->filled('idContrato')) {
            return response()->json(['message' => 'Debe seleccionar un contrato'], 400);
        }

        $contrato = Contract::with('salario')->find($request->idContrato);

        if (!$contrato || !$contrato->salario) {
            return response()->json([
                'message' => 'El contrato no tiene un salario asociado'
            ], 400);
        }

        $configuracionNomina = ConfiguracionNomina::first();
        if (!$configuracionNomina) {
            return response()->json([
                'message' => 'No existe configuración de nómina'
            ], 400);
        }


        $fechaInicio = Carbon::parse($request->fechaInicial);
        $numCuotas = $request->cuotas === 'si' ? (int) $request->numero_cuotas : 1;

        $fechaFin = $fechaInicio->copy()->addMonths($numCuotas);



        $urlArchivo = null;
        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $path = $file->store('otras_deducciones', 'public');
            $urlArchivo = Storage::url($path);
        }
        $deduccion = new OtraDeduccion();
        $deduccion->idContrato       = $request->idContrato;
        $deduccion->idTipoConcepto   = $request->idTipoConcepto;
        $deduccion->valor            = $request->valor;
        $deduccion->valorFinal       = $request->valor_final;
        $deduccion->coutas           = $request->cuotas === 'si' ? 1 : 0;
        $deduccion->numCoutas        = $numCuotas;
        $deduccion->urlArchivo       = $urlArchivo;
        $deduccion->estado           = 'APROBADO';
        $deduccion->fechaInicio      = $fechaInicio;
        $deduccion->fechaFin         = $fechaFin;
        $deduccion->save();



        $deduccion->save();


        return response()->json([
            'message' => 'Deducción creada exitosamente',
            'data'    => $deduccion,
        ], 201);
    }




    public function updateStatusDeduccion(Request $request, string $id)
    {
        $validated = $request->validate([
            'estado' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $deduccion = OtraDeduccion::findOrFail($id);

            $deduccion->update([
                'estado'               => $validated['estado'],
                'observacion' => $request->comentario,
            ]);

            DB::commit();

            return response()->json($deduccion, 200);
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();

            return response()->json([
                'error' => 'Hubo un problema al procesar la solicitud. Intente nuevamente.',
            ], 500);
        }
    }
}
