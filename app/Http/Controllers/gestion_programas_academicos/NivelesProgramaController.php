<?php

namespace App\Http\Controllers\gestion_programas_academicos;

use App\Http\Controllers\Controller;
use App\Models\Periodo;
use App\Models\TipoGrado;
use App\Models\Jornada;
use App\Models\AsignacionPeriodoPrograma;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NivelesProgramaController extends Controller
{
    /**
     * Obtiene el detalle de una asignación específica y los recursos para editarla.
     */
   public function getDetalleAsignacion($idPrograma): JsonResponse
{
    try {
        // BUSCAMOS POR idPrograma, no por id de la tabla asignacion
        $asignacion = AsignacionPeriodoPrograma::with([
            'programa.tipoGrado', 
            'periodo', 
            'sede', 
            'jornadas'
        ])->where('idPrograma', $idPrograma)->first(); // Usamos first() para obtener la asignación actual

        if (!$asignacion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este programa aún no tiene una asignación de periodo o jornada.'
            ], 404);
        }

        // El resto del código se mantiene igual...
        $idEmpresa = $asignacion->idEmpresa ?? $asignacion->programa->idCompany;

        $recursos = [
            'periodos' => Periodo::where('idEmpresa', $idEmpresa)->select('id', 'nombrePeriodo as nombre')->get(),
            'tipos_grado' => TipoGrado::select('id', 'nombreTipoGrado as nombre')->get(),
            'jornadas_disponibles' => Jornada::where('idEmpresa', $idEmpresa)->where('estado', 1)->select('id', 'nombreJornada as nombre')->get(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [ 'detalle' => $asignacion, 'recursos' => $recursos ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
}