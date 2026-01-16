<?php

namespace App\Http\Controllers\gestion_reportes;

use App\Http\Controllers\Controller;
use App\Models\PersonaAuxiliar;
use App\Models\ReporteSuper;
use App\Models\VehiculoAuxiliar;
use App\Util\KeyUtil;
use Illuminate\Http\Request;

class ReporteSuperIntendenciaController extends Controller
{

    public function getPersonaAux($identificacion)
    {
        $persona = PersonaAuxiliar::where('identificacion', $identificacion)->first();

        if (!$persona) {
            return response()->json([
                'success' => false,
                'message' => 'Persona no encontrada',
            ], 404);
        }

        return response()->json($persona);
    }



    public function getVehiculoAux($placa)
    {

        $placa = strtoupper($placa);


        if (preg_match('/^[A-Z]{3}[0-9]{3}$/', $placa)) {
            $placa = substr($placa, 0, 3) . '-' . substr($placa, 3);
        }

        $vehiculo = VehiculoAuxiliar::where('placa', $placa)->first();

        if (!$vehiculo) {
            return response()->json([
                'success' => false,
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        return response()->json($vehiculo);
    }





    public function storePersonaAux(Request $request)
    {
        $request->validate([
            'identificacion' => 'required|string|max:50|unique:personaAuxiliar,identificacion',
            'nombre1'        => 'required|string|max:100',
            'apellido1'      => 'required|string|max:100',

        ]);

        try {
            $persona = new PersonaAuxiliar();
            $persona->identificacion = $request->identificacion;
            $persona->nombre1 = $request->nombre1;
            $persona->apellido1 = $request->apellido1;

            $persona->save();

            return response()->json([
                'success' => true,
                'message' => 'Persona creada correctamente',
                'data'    => $persona
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear persona',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function storeVehiculoAux(Request $request)
    {
        $request->validate([
            'placa' => 'required|string|max:20|unique:vehiculoAuxiliar,placa',
        ]);

        try {
            $vehiculo = new VehiculoAuxiliar();
            $vehiculo->placa = $request->placa;
            $vehiculo->save();

            return response()->json([
                'success' => true,
                'message' => 'Vehículo creado correctamente',
                'data'    => $vehiculo
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear vehículo',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    public function getReportSuperIntendencia(Request $request)
    {
        $search = $request->input('search');

        $reports = ReporteSuper::with('vehiculoAux', 'personaAux', 'responsable.persona', 'empresa')
            ->where('estado', 'PENDIENTE')
            ->when($search, function ($query, $search) {
                $query->whereHas('vehiculoAux', function ($q) use ($search) {
                    $q->where('placa', 'LIKE', "%{$search}%");
                })
                    ->orWhereHas('personaAux', function ($q) use ($search) {
                        $q->where('identificacion', 'LIKE', "%{$search}%");
                    });
            })
            ->get();

        $data = $reports->map(function ($report) {
            return [
                'id' => $report->id,
                'nit' => $report->empresa->nit ?? null,
                'placaVehiculo' => $report->vehiculoAux->placa ?? null,
                'cedulaResponsable' => $report->responsable->persona->identificacion ?? null,
                'nombreResponsable' => trim(($report->responsable->persona->nombre1 ?? '') . ' ' . ($report->responsable->persona->apellido1 ?? '')),
                'cedulaConductor' => $report->personaAux->identificacion ?? null,
                'nombreConductor' => trim(($report->personaAux->nombre1 ?? '') . ' ' . ($report->personaAux->apellido1 ?? '')),
                'item1' => $report->item1,
                'item2' => $report->item2,
                'item3' => $report->item3,
                'item4' => $report->item4,
                'item5' => $report->item5,
                'item6' => $report->item6,
                'item7' => $report->item7,
                'item8' => $report->item8,
                'item9' => $report->item9,
                'item10' => $report->item10,
                'item11' => $report->item11,
                'estado' => $report->estado,
                'fechaCreacion' => $report->created_at,
                'fechaActualizacion' => $report->updated_at,
            ];
        });

        return response()->json($data);
    }




    public function storeReportSuperIntendencia(Request $request)
    {
        $request->validate([

            'idPersona'  => 'required|integer|exists:personaAuxiliar,id',
            'idVehiculo' => 'required|integer|exists:vehiculoAuxiliar,id',

        ]);

        try {
            $reporte = new ReporteSuper();
            $reporte->idEmpresa  = KeyUtil::idCompany();
            $reporte->idPersona  = $request->idPersona;
            $reporte->idVehiculo = $request->idVehiculo;
            $reporte->idUsuario  =  auth()->user()->id;
            $reporte->estado  = 'PENDIENTE';
            $reporte->save();

            return response()->json([
                'success' => true,
                'message' => 'Reporte creado correctamente',
                'data'    => $reporte->load('vehiculoAux', 'personaAux', 'responsable.persona')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el reporte',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    public function updateReportSuperIntendencia(Request $request, $id)
    {
  

        try {
            $reporte = ReporteSuper::findOrFail($id);

            $reporte->idPersona  = $request->idPersona;
            $reporte->idVehiculo = $request->idVehiculo;
            $reporte->idUsuario  = auth()->user()->id;

            $reporte->save();

            return response()->json([
                'success' => true,
                'message' => 'Reporte actualizado correctamente',
                'data'    => $reporte->load('vehiculoAux', 'personaAux', 'responsable.persona')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el reporte',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    public function deleteReportSuperIntendencia($id)
    {
        $reporte = ReporteSuper::find($id);

        if (!$reporte) {
            return response()->json(['message' => 'Reporte no encontrado'], 404);
        }

        $reporte->delete();

        return response()->json(['message' => 'Reporte eliminado correctamente']);
    }
}
