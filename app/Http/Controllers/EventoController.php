<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\GrupoMultimedia;
use App\Models\MultimediaHistorias;
use App\Models\ParticipanteEvento;
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
        $search    = $request->input('search');
        $archived  = filter_var($request->input('archived', false), FILTER_VALIDATE_BOOLEAN);

        $query = Evento::where('idCompany', $idCompany)
            ->with(['area', 'grupoMultimedia']);

        if ($archived) {
            $query->where('estado', 'FINALIZADO');
        } else {
            $query->where(function($q) {
                $q->where('estado', '!=', 'FINALIZADO')
                  ->orWhere(function($sub) {
                      $sub->where('estado', 'FINALIZADO')
                          ->where('updated_at', '>=', now()->subHours(12));
                  });
            });
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        $eventos = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', $request->input('limit', 6)));

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
            'hora_final'   => 'nullable',
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
            $evento->hora_final   = $request->input('hora_final');
            $evento->linkRegistro = $request->input('linkRegistro');
            $evento->tipoEvento   = $request->input('tipoEvento', 'PRESENCIAL');
            $evento->estado       = $request->input('estado', 'PENDIENTE');
            $evento->esPublico    = filter_var($request->input('esPublico', true), FILTER_VALIDATE_BOOLEAN);
            $evento->idArea       = $request->input('idArea') ?: null;
            $evento->formUrl      = $request->input('formUrl');
            $evento->formProvider = $request->input('formProvider');

            // Manejo de archivo promocional del evento
            if ($request->hasFile('archivo')) {
                $path = $request->file('archivo')->store('eventos', ['disk' => 'public']);
                $evento->url = '/storage/' . $path;
            }

            $evento->save();

            // LÓGICA AUTOMÁTICA: Crear Historia Multimedia si se solicita
            if (filter_var($request->input('crearHistoria'), FILTER_VALIDATE_BOOLEAN)) {
                $this->crearHistoriaMultimedia($evento);
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
        if ($request->has('hora_final'))   $evento->hora_final   = $request->input('hora_final');
        if ($request->has('linkRegistro')) $evento->linkRegistro = $request->input('linkRegistro') ?: null;
        if ($request->has('tipoEvento'))   $evento->tipoEvento   = $request->input('tipoEvento');
        if ($request->has('estado'))       $evento->estado       = $request->input('estado');
        if ($request->has('esPublico'))    $evento->esPublico    = filter_var($request->input('esPublico'), FILTER_VALIDATE_BOOLEAN);
        $evento->idArea = $request->input('idArea') ?: null;
        if ($request->has('formUrl'))      $evento->formUrl      = $request->input('formUrl');
        if ($request->has('formProvider')) $evento->formProvider = $request->input('formProvider');

        if ($request->hasFile('archivo')) {
            $path = $request->file('archivo')->store('eventos', ['disk' => 'public']);
            $evento->url = '/storage/' . $path;
        }

        $evento->save();

        if (filter_var($request->input('crearHistoria'), FILTER_VALIDATE_BOOLEAN)) {
            $this->crearHistoriaMultimedia($evento);
        }

        return response()->json([
            'message' => 'Evento actualizado',
            'evento'  => $evento->load('area', 'grupoMultimedia')
        ]);
    }

    /**
     * Lógica para crear historia multimedia desde un evento
     */
    private function crearHistoriaMultimedia(Evento $evento)
    {
        // Solo si tiene URL y no tiene ya un grupo vinculado
        if (!$evento->url || $evento->idGrupoMultimedia) {
            return;
        }

        try {
            // 1. Crear el Grupo Multimedia
            $grupo = new GrupoMultimedia();
            $grupo->idCompany   = $evento->idCompany;
            $grupo->idUser      = $evento->idUser;
            $grupo->nombreGrupo = $evento->nombre;
            $grupo->tipo        = 'historia';
            $grupo->descripcion = "Historia generada automáticamente desde el evento: " . $evento->nombre;
            $grupo->save();

            // 2. Crear la Historia (Item multimedia)
            $historia = new MultimediaHistorias();
            $historia->idCompany         = $evento->idCompany;
            $historia->idUser            = $evento->idUser;
            $historia->idGrupoMultimedia = $grupo->id;
            $historia->nombre            = $evento->nombre;
            $historia->urlMultimedia     = $evento->url;
            $historia->tipo              = 'historia';
            $historia->orden             = 1;
            $historia->descripcion       = $evento->descripcion;
            $historia->save();

            // 3. Vincular el grupo al evento
            $evento->idGrupoMultimedia = $grupo->id;
            $evento->save();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error creando historia desde evento: ' . $e->getMessage());
        }
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

    /**
     * Verificar si el usuario actual está inscrito en el evento
     */
    public function checkRegistration($id)
    {
        $idPersona = auth()->user()->idpersona;
        
        if (!$idPersona) return response()->json(['inscrito' => false]);

        $inscrito = ParticipanteEvento::where('idEvento', $id)
            ->where('idPersona', $idPersona)
            ->exists();

        return response()->json(['inscrito' => $inscrito]);
    }

    /**
     * Inscribir al usuario actual en el evento
     */
    public function register(Request $request, $id)
    {
        $idPersona = auth()->user()->idpersona;

        if (!$idPersona) {
            return response()->json(['message' => 'El usuario no tiene una persona asociada'], 400);
        }
        $registro = ParticipanteEvento::updateOrCreate(
            ['idEvento' => $id, 'idPersona' => $idPersona],
            ['fechaRegistro' => now(), 'estado' => 'CONFIRMADO']
        );

        return response()->json([
            'message' => 'Te has inscrito correctamente al evento',
            'registro' => $registro
        ]);
    }

    /**
     * Obtener lista de inscritos (Solo para Admin/Creador)
     */
    public function getAttendees($id)
    {
        // Solo el creador o alguien de la misma empresa (según lógica de negocio)
        $attendees = ParticipanteEvento::where('idEvento', $id)
            ->with(['persona' => function($query) {
                $query->select('id', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'email');
            }])
            ->get();

        return response()->json($attendees);
    }
}
