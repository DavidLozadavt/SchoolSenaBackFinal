<?php

namespace App\Http\Controllers\gestion_programas_academicos;


use App\Http\Controllers\Controller;
use App\Models\NivelEducativo;
use App\Models\TipoFormacion;
use App\Models\EstadoPrograma;
use App\Models\Programa;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class PensumController extends Controller
{
    public function getMetadata()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
               'niveles_educativos' => NivelEducativo::where('activo', 1)
                    ->select('id', 'nombreNivel as nombre')
                    ->get(),
                
                'tipos_formacion'    => TipoFormacion::where('activo', 1)
                    ->select('id', 'nombreTipoFormacion as nombre')
                    ->get(),
                'estados_programa'   => EstadoPrograma::all()
            ]
        ], 200);
    }

    public function index()
{
    try {
$programas = Programa::with(['nivel', 'tipoFormacion', 'estado'])->get();
        return response()->json([
            'status' => 'success',
            'data' => $programas
        ], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

public function indexByRegional(int $idRegional)
{
    try {
        $programas = Programa::with(['nivel', 'tipoFormacion', 'estado'])
            ->where('idCompany', $idRegional)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $programas
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}



    public function store(Request $request)
    {
        $request->validate([
            'nombrePrograma'   => 'required|string|max:255',
            'codigoPrograma'   => 'required|string|max:255',
            'descripcionPrograma' => 'nullable|string',
            'idNivelEducativo' => 'required|exists:nivelEducativo,id',
            'idTipoFormacion'  => 'required|exists:tipoFormacion,id',
            'idEstadoPrograma' => 'required|exists:estadoPrograma,id',
            'documento'        => 'nullable|file|mimes:pdf|max:5120',
        ]);

        try {
            $nuevoPrograma = Programa::create([
                'nombrePrograma'      => $request->nombrePrograma,
                'codigoPrograma'      => $request->codigoPrograma,
                'descripcionPrograma' => $request->descripcionPrograma,
                'documento'           => null,
                'idNivelEducativo'    => $request->idNivelEducativo,
                'idTipoFormacion'     => $request->idTipoFormacion,
                'idEstadoPrograma'    => $request->idEstadoPrograma,
                'idCompany'           => KeyUtil::idCompany(),
            ]);

            if ($request->hasFile('documento')) {
                $nombreArchivo = Str::limit(Str::slug($request->nombrePrograma), 80) . '_' . $nuevoPrograma->id . '.pdf';
                $request->file('documento')->storeAs('programas/documentos', $nombreArchivo, 'public');
                $nuevoPrograma->update(['documento' => '/storage/programas/documentos/' . $nombreArchivo]);
            }

            $nuevoPrograma->load(['nivel', 'tipoFormacion', 'estado']);

            return response()->json([
                'status' => 'success',
                'message' => '¡Programa guardado con éxito!',
                'data' => $nuevoPrograma
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar el programa: ' . $e->getMessage()
            ], 500);
        }
    }
    

public function update(Request $request, $id)
{
    $request->validate([
        'nombrePrograma'   => 'required|string|max:255',
        'codigoPrograma'   => 'required|string|max:255',
        'descripcionPrograma' => 'nullable|string',
        'idNivelEducativo' => 'required|exists:nivelEducativo,id',
        'idTipoFormacion'  => 'required|exists:tipoFormacion,id',
        'idEstadoPrograma' => 'required|exists:estadoPrograma,id',
        'documento'        => 'nullable|file|mimes:pdf|max:5120',
    ]);

    try {
        $programa = Programa::findOrFail($id);

        $data = [
            'nombrePrograma'      => $request->nombrePrograma,
            'codigoPrograma'      => $request->codigoPrograma,
            'descripcionPrograma' => $request->descripcionPrograma,
            'idNivelEducativo'    => $request->idNivelEducativo,
            'idTipoFormacion'     => $request->idTipoFormacion,
            'idEstadoPrograma'    => $request->idEstadoPrograma,
        ];

        if ($request->hasFile('documento')) {
            if ($programa->documento) {
                $rutaVieja = str_replace('/storage/', '', $programa->documento);
                Storage::disk('public')->delete($rutaVieja);
            }
            $nombreArchivo = Str::limit(Str::slug($request->nombrePrograma), 80) . '_' . $programa->id . '.pdf';
            $request->file('documento')->storeAs('programas/documentos', $nombreArchivo, 'public');
            $data['documento'] = '/storage/programas/documentos/' . $nombreArchivo;
        }

        $programa->update($data);

        $programa->load(['nivel', 'tipoFormacion', 'estado']);

        return response()->json([
            'status' => 'success',
            'message' => '¡Programa actualizado con éxito!',
            'data' => $programa
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error al actualizar: ' . $e->getMessage()
        ], 500);
    }
}

public function destroy($id)
{
    try {
        $programa = Programa::findOrFail($id);
        $programa->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Programa eliminado correctamente'
        ], 200);

    } catch (QueryException $e) {

        if ($e->errorInfo[1] == 1451) {
            return response()->json([
                'status' => 'warning',
                'message' => 'No se puede eliminar el programa porque tiene información académica asociada. Puede desactivarlo.'
            ], 409);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Error al eliminar el programa'
        ], 500);
    }
}

/**
     * Para gestión de programas académicos
     * Carga la información de la apertura de un programa
     */
   public function getInformacionApertura($idPrograma)
{
    try {
        
        $asignacion = \App\Models\AsignacionPeriodoPrograma::with(['programa', 'periodo', 'sede'])
            ->where('idPrograma', $idPrograma)
            ->firstOrFail(); 

        return response()->json([
            'status' => 'success',
            'data' => $asignacion
        ], 200);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'No se encontró una apertura configurada para este programa (ID: ' . $idPrograma . ')'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error interno: ' . $e->getMessage()
        ], 500);
    }
}

    
    public function updateInformacionApertura(Request $request, $id)
    {
        try {
            $asignacion = \App\Models\AsignacionPeriodoPrograma::findOrFail($id);
            
            $asignacion->update($request->only([
                'observacion',
                'estado',
                'pension',
                'diaCobro',
                'fechaInicialClases',
                'fechaFinalClases',
                'fechaInicialInscripciones',
                'fechaFinalInscripciones',
                'fechaInicialMatriculas',
                'fechaFinalMatriculas',
                'valorPension',
                'diasMoraMatricula',
                'diasMoraPension',
                'porcentajeMoraMatricula',
                'porcentajeMoraPension'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración de asignación actualizada con éxito',
                'data' => $asignacion
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }


}