<?php

namespace App\Http\Controllers\gestion_afiliacion;

use App\Http\Controllers\Controller;
use App\Models\AsignacionProcesoTipoDocumento;
use App\Models\Proceso;
use App\Models\Status;
use App\Models\TipoAfiliacion;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;

class TipoAfiliacionController extends Controller
{
    public function index()
    {
        $tipos = TipoAfiliacion::all();
        return response()->json($tipos);
    }

    /**
     * Guarda un nuevo tipo de afiliación.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipoAfiliacion' => 'nullable|string|max:60',
            'observacion' => 'nullable|string',
        ]);

        $tipo = TipoAfiliacion::create($request->all());

        $proceso = Proceso::create([
            'nombreProceso' => $request->tipoAfiliacion,

        ]);

        $documento = new TipoDocumento();
        $documento->tituloDocumento = 'TARJETA DE OPERACIÓN';
        $documento->idEstado = Status::ID_ACTIVE;
        $documento->tipoFecha = 'FECHA VIGENCIA';
        $documento->descripcion = 'N/A';
        $documento->save();


        $asignacionDocumento = new AsignacionProcesoTipoDocumento();
        $asignacionDocumento->idProceso = $proceso->id;
        $asignacionDocumento->idTipoDocumento = $documento->id;
        $asignacionDocumento->actualizar = 1;
        $asignacionDocumento->save();

        return response()->json([
            'message' => 'Tipo de afiliación creado con éxito',
            'tipoAfiliacion' => $tipo
        ], 201);
    }

    /**
     * Muestra un tipo de afiliación específico.
     */
    public function show($id)
    {
        $tipo = TipoAfiliacion::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de afiliación no encontrado'], 404);
        }

        return response()->json($tipo);
    }

    /**
     * Actualiza un tipo de afiliación existente.
     */
    public function update(Request $request, $id)
    {
        $tipo = TipoAfiliacion::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de afiliación no encontrado'], 404);
        }

        $request->validate([
            'tipoAfiliacion' => 'nullable|string|max:60',
            'observacion' => 'nullable|string',
        ]);

        $tipo->update($request->all());

        return response()->json([
            'message' => 'Tipo de afiliación actualizado con éxito',
            'tipoAfiliacion' => $tipo
        ]);
    }

    /**
     * Elimina un tipo de afiliación.
     */
    public function destroy($id)
    {
        $tipo = TipoAfiliacion::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de afiliación no encontrado'], 404);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de afiliación eliminado con éxito']);
    }
}
