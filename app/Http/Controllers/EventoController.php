<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\GrupoMultimedia;
use App\Models\MultimediaHistorias;
use App\Util\KeyUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EventoController extends Controller
{
    /**
     * Listar eventos de la empresa
     */
    public function index(Request $request)
    {
        $idCompany = KeyUtil::idCompany();
        $eventos = Evento::where('idCompany', $idCompany)
            ->with(['area', 'grupoMultimedia'])
            ->orderBy('fechaInicial', 'desc')
            ->get();

        return response()->json($eventos);
    }

    /**
     * Obtener un evento específico
     */
    public function show($id)
    {
        $evento = Evento::with(['area', 'grupoMultimedia'])->findOrFail($id);
        return response()->json($evento);
    }

    /**
     * Crear un nuevo evento
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'       => 'required|string|max:255',
            'fechaInicial' => 'required|date',
            'hora'         => 'required',
            'idArea'       => 'nullable|integer',
        ]);

        DB::beginTransaction();

        try {
            $idCompany = KeyUtil::idCompany();
            $idUser    = auth()->id();

            $evento = new Evento();
            $evento->idCompany    = $idCompany;
            $evento->idUser       = $idUser;
            $evento->nombre       = $request->input('nombre');
            $evento->descripcion  = $request->input('descripcion');
            $evento->fechaInicial = $request->input('fechaInicial');
            $evento->fechaFinal   = $request->input('fechaFinal');
            $evento->hora         = $request->input('hora');
            $evento->linkRegistro = $request->input('linkRegistro');
            $evento->tipoEvento   = $request->input('tipoEvento', 'PRESENCIAL');
            $evento->estado       = $request->input('estado', 'PENDIENTE');
            $evento->esPublico    = filter_var($request->input('esPublico', true), FILTER_VALIDATE_BOOLEAN);
            $evento->idArea       = $request->input('idArea') ?: null;

            // Manejo de archivo promocional del evento
            if ($request->hasFile('archivo')) {
                $path = $request->file('archivo')->store('eventos', ['disk' => 'public']);
                $evento->url = '/storage/' . $path;
            }

            $evento->save();

            // LÓGICA AUTOMÁTICA: Crear Historia Multimedia si se solicita
            if ($request->boolean('crearHistoria') && $evento->url) {
                
                // 1. Crear el Grupo Multimedia
                $grupo = new GrupoMultimedia();
                $grupo->idCompany   = $idCompany;
                $grupo->idUser      = $idUser;
                $grupo->nombreGrupo = "Evento: " . $evento->nombre;
                $grupo->tipo        = 'historia';
                $grupo->descripcion = "Historia generada automáticamente desde el evento: " . $evento->nombre;
                $grupo->save();

                // 2. Crear la Historia (Item multimedia)
                $historia = new MultimediaHistorias();
                $historia->idCompany         = $idCompany;
                $historia->idUser            = $idUser;
                $historia->idGrupoMultimedia = $grupo->id;
                $historia->nombre            = $evento->nombre; // Campo 'nombre' en multimedia_historias
                $historia->urlMultimedia     = $evento->url;   // Campo 'urlMultimedia'
                $historia->tipo              = 'historia'; // ENUM: 'historia' | 'reel'
                $historia->orden             = 1;
                $historia->save();

                // 3. Vincular el grupo al evento
                $evento->idGrupoMultimedia = $grupo->id;
                $evento->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Evento creado correctamente',
                'evento'  => $evento->load('area', 'grupoMultimedia')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('EventoController@store ERROR: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => request()->all(),
            ]);
            return response()->json([
                'message' => 'Error al crear el evento',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Actualizar evento
     */
    public function update(Request $request, $id)
    {
        $evento = Evento::findOrFail($id);

        if ($request->has('nombre'))       $evento->nombre       = $request->input('nombre');
        if ($request->has('descripcion'))  $evento->descripcion  = $request->input('descripcion');
        if ($request->has('fechaInicial')) $evento->fechaInicial = $request->input('fechaInicial');
        if ($request->has('fechaFinal'))   $evento->fechaFinal   = $request->input('fechaFinal') ?: null;
        if ($request->has('hora'))         $evento->hora         = $request->input('hora');
        if ($request->has('linkRegistro')) $evento->linkRegistro = $request->input('linkRegistro') ?: null;
        if ($request->has('tipoEvento'))   $evento->tipoEvento   = $request->input('tipoEvento');
        if ($request->has('estado'))       $evento->estado       = $request->input('estado');
        if ($request->has('esPublico'))    $evento->esPublico    = filter_var($request->input('esPublico'), FILTER_VALIDATE_BOOLEAN);
        $evento->idArea = $request->input('idArea') ?: null;

        if ($request->hasFile('archivo')) {
            $path = $request->file('archivo')->store('eventos', ['disk' => 'public']);
            $evento->url = '/storage/' . $path;
        }

        $evento->save();

        return response()->json([
            'message' => 'Evento actualizado',
            'evento'  => $evento->load('area')
        ]);
    }

    /**
     * Eliminar evento
     */
    public function destroy($id)
    {
        $evento = Evento::findOrFail($id);
        $evento->delete();

        return response()->json(['message' => 'Evento eliminado']);
    }
}
