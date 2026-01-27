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
            'programa', // Cargamos programa sin tipoGrado para evitar errores si no existe la columna
            'periodo', 
            'sede', 
            'jornadas'
        ])->where('idPrograma', $idPrograma)->first(); // Usamos first() para obtener la asignación actual

        if (!$asignacion) {
            // Si no hay asignación, aún así devolvemos los recursos para que el frontend pueda crear una
            $programa = \App\Models\Programa::find($idPrograma);
            $idEmpresa = $programa->idCompany ?? null;
            
            $recursos = [
                'periodos' => $idEmpresa ? Periodo::where('idEmpresa', $idEmpresa)->select('id', 'nombrePeriodo as nombre')->get() : [],
                'tipos_grado' => TipoGrado::select('id', 'nombreTipoGrado as nombre')->get(),
                'jornadas_disponibles' => $idEmpresa ? Jornada::where('idEmpresa', $idEmpresa)->where('estado', 1)->select('id', 'nombreJornada as nombre')->get() : [],
            ];

            return response()->json([
                'status' => 'success',
                'data' => [ 
                    'detalle' => null, 
                    'recursos' => $recursos 
                ]
            ], 200);
        }

        // El resto del código se mantiene igual...
        $idEmpresa = $asignacion->idEmpresa ?? $asignacion->programa->idCompany ?? null;

        $recursos = [
            'periodos' => $idEmpresa ? Periodo::where('idEmpresa', $idEmpresa)->select('id', 'nombrePeriodo as nombre')->get() : [],
            'tipos_grado' => TipoGrado::select('id', 'nombreTipoGrado as nombre')->get(),
            'jornadas_disponibles' => $idEmpresa ? Jornada::where('idEmpresa', $idEmpresa)->where('estado', 1)->select('id', 'nombreJornada as nombre')->get() : [],
        ];

        return response()->json([
            'status' => 'success',
            'data' => [ 'detalle' => $asignacion, 'recursos' => $recursos ]
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error en getDetalleAsignacion: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'idPrograma' => $idPrograma
        ]);
        
        // Mensaje más claro si la tabla no existe
        $errorMessage = $e->getMessage();
        if (strpos($errorMessage, "doesn't exist") !== false || strpos($errorMessage, "BASE TABLE OR VIEW NOT FOUND") !== false) {
            $errorMessage = 'La tabla asignacionPeriodoPrograma no existe en la base de datos. Por favor, ejecute la migración: php artisan migrate --path=database/migrations/2026_01_09_123938_create_asignacion_periodo_programa_table.php';
        }
        
        return response()->json([
            'status' => 'error', 
            'message' => 'Error al cargar la configuración: ' . $errorMessage
        ], 500);
    }
}
}