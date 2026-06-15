<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\GrupoMultimedia;
use App\Models\MultimediaHistorias;
use App\Models\ParticipanteEvento;
use App\Models\Person;
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

        // Auto-finalize events that have already ended
        $endDatetimeSql = "CASE 
            WHEN hora_final IS NOT NULL THEN CONCAT(COALESCE(fechaFinal, fechaInicial), ' ', hora_final)
            ELSE DATE_ADD(CONCAT(fechaInicial, ' ', hora), INTERVAL 2 HOUR)
        END";

        Evento::where('idCompany', $idCompany)
            ->where('estado', '!=', 'FINALIZADO')
            ->whereRaw("{$endDatetimeSql} < ?", [Carbon::now()])
            ->update(['estado' => 'FINALIZADO']);

        $query = Evento::where('idCompany', $idCompany)
            ->with([
                'area',
                'grupoMultimedia',
                'formularioInterno:id,titulo,colorTema,estado',
            ]);

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
        foreach (['idArea', 'idFormularioInterno', 'idGrupoMultimedia'] as $key) {
            if ($request->has($key)) {
                $val = $request->input($key);
                if ($val === 'null' || $val === 'undefined' || $val === '') {
                    $request->merge([$key => null]);
                }
            }
        }

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
            $evento->idArea       = $request->input('idArea');
            $evento->formUrl      = $request->input('formUrl');
            $evento->formProvider = $request->input('formProvider');
            $evento->idFormularioInterno = $request->input('idFormularioInterno');

            // Manejo de archivo promocional del evento
            if ($request->hasFile('archivo')) {
                $path = $request->file('archivo')->store('eventos', ['disk' => 'public']);
                $evento->url = '/storage/' . $path;
            }

            $evento->save();

            // LÓGICA AUTOMÁTICA: Crear Historia Multimedia si se solicita
            if (filter_var($request->input('crearHistoria'), FILTER_VALIDATE_BOOLEAN)) {
                $this->crearHistoriaMultimedia($evento, $request->input('cancion'));
            }

            // Asociar actividades/items existentes
            if ($request->has('actividades_ids')) {
                $actividadesIds = json_decode($request->input('actividades_ids'), true) ?: [];
                if (!empty($actividadesIds)) {
                    \App\Models\Item::whereIn('id', $actividadesIds)->update(['idEvento' => $evento->idEvento]);
                }
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
        foreach (['idArea', 'idFormularioInterno', 'idGrupoMultimedia'] as $key) {
            if ($request->has($key)) {
                $val = $request->input($key);
                if ($val === 'null' || $val === 'undefined' || $val === '') {
                    $request->merge([$key => null]);
                }
            }
        }

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
        if ($request->has('idArea'))       $evento->idArea       = $request->input('idArea');
        if ($request->has('formUrl'))      $evento->formUrl      = $request->input('formUrl');
        if ($request->has('formProvider')) $evento->formProvider = $request->input('formProvider');
        if ($request->has('idFormularioInterno')) $evento->idFormularioInterno = $request->input('idFormularioInterno');

        if ($request->hasFile('archivo')) {
            $path = $request->file('archivo')->store('eventos', ['disk' => 'public']);
            $evento->url = '/storage/' . $path;
        }

        $evento->save();

        if (filter_var($request->input('crearHistoria'), FILTER_VALIDATE_BOOLEAN)) {
            $this->crearHistoriaMultimedia($evento, $request->input('cancion'));
        }

        // Asociar actividades/items existentes (y desasociar las que ya no están seleccionadas)
        if ($request->has('actividades_ids')) {
            $actividadesIds = json_decode($request->input('actividades_ids'), true) ?: [];
            // Desasociar todas las anteriores de este evento
            \App\Models\Item::where('idEvento', $evento->idEvento)->update(['idEvento' => null]);
            // Asociar las nuevas seleccionadas
            if (!empty($actividadesIds)) {
                \App\Models\Item::whereIn('id', $actividadesIds)->update(['idEvento' => $evento->idEvento]);
            }
        }

        return response()->json([
            'message' => 'Evento actualizado',
            'evento'  => $evento->load('area', 'grupoMultimedia')
        ]);
    }

    /**
     * Lógica para crear historia multimedia desde un evento
     */
    private function crearHistoriaMultimedia(Evento $evento, $cancion = null)
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
            if ($cancion) {
                $historia->cancion = is_string($cancion) ? $cancion : json_encode($cancion);
            }
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
     * Asegura que el usuario logueado tenga una persona asociada
     */
    private function obtenerOAsociarPersona()
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        if ($user->idpersona) {
            return $user->idpersona;
        }

        // Buscar si ya existe una persona con el mismo email
        $persona = Person::where('email', $user->email)->first();

        if (!$persona) {
            // Obtener primeros IDs válidos para evitar fallos de claves foráneas
            $tipoId = DB::table('tipoIdentificacion')->value('id') ?? 1;
            $ciudadId = DB::table('ciudad')->value('id') ?? 1;

            $identificacion = 'ADMIN_' . $user->id . '_' . time();
            $nameParts = explode(' ', $user->name ?: 'Admin Sistema');
            $nombre1 = $nameParts[0] ?? 'Admin';
            $nombre2 = $nameParts[1] ?? '';
            $apellido1 = $nameParts[2] ?? 'Sistema';
            $apellido2 = $nameParts[3] ?? '';

            $persona = new Person();
            $persona->identificacion = $identificacion;
            $persona->nombre1 = $nombre1;
            $persona->nombre2 = $nombre2;
            $persona->apellido1 = $apellido1;
            $persona->apellido2 = $apellido2;
            $persona->fechaNac = '1990-01-01';
            $persona->direccion = 'Calle Falsa 123';
            $persona->email = $user->email ?? 'admin@virtualt.org';
            $persona->telefonoFijo = '5555555';
            $persona->celular = '3000000000';
            $persona->perfil = 'N/A';
            $persona->sexo = 'M';
            $persona->rh = 'O+';
            $persona->rutaFoto = '/default/user.svg';
            $persona->idTipoIdentificacion = $tipoId;
            $persona->idCiudad = $ciudadId;
            $persona->idCiudadNac = $ciudadId;
            $persona->idCiudadUbicacion = $ciudadId;
            $persona->save();
        }

        // Vincular persona al usuario
        $user->idpersona = $persona->id;
        $user->save();

        return $persona->id;
    }

    /**
     * Verificar si el usuario actual está inscrito en el evento
     */
    public function checkRegistration($id)
    {
        $idPersona = $this->obtenerOAsociarPersona();
        
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
        $idPersona = $this->obtenerOAsociarPersona();

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
     * Obtener inscripciones (respuestas del formulario) de un evento para NexiService.
     * Retorna las respuestas + las preguntas del formulario para resolver títulos.
     */
    public function inscripciones($id)
    {
        $evento = Evento::findOrFail($id);

        if (!$evento->idFormularioInterno) {
            return response()->json(['preguntas' => [], 'respuestas' => []]);
        }

        $formulario = \App\Models\Formulario::with('preguntas')->find($evento->idFormularioInterno);
        if (!$formulario) {
            return response()->json(['preguntas' => [], 'respuestas' => []]);
        }

        $respuestas = \App\Models\FormularioRespuesta::where('idFormulario', $formulario->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'idFormulario', 'respuestas', 'created_at', 'updated_at']);

        return response()->json([
            'preguntas' => $formulario->preguntas->map(fn($p) => ['id' => $p->id, 'titulo' => $p->titulo]),
            'respuestas' => $respuestas,
        ]);
    }

    /**
     * Eliminar una inscripción (respuesta de formulario) desde NexiService.
     * También elimina el ParticipanteEvento asociado si existe.
     */
    public function eliminarInscripcion(Request $request, $id, $respuestaId)
    {
        $evento = Evento::findOrFail($id);

        $respuesta = \App\Models\FormularioRespuesta::where('id', $respuestaId)
            ->when($evento->idFormularioInterno, fn($q) => $q->where('idFormulario', $evento->idFormularioInterno))
            ->firstOrFail();

        // Eliminar también el ParticipanteEvento si hay email en la respuesta
        if ($evento->idFormularioInterno) {
            $emailValor = null;
            foreach (($respuesta->respuestas ?? []) as $r) {
                $preg = \App\Models\FormularioPregunta::find($r['idPregunta'] ?? 0);
                if (!$preg) continue;
                $titulo = $this->normalizarTituloPregunta($preg->titulo);
                if (str_contains($titulo, 'correo') || str_contains($titulo, 'email') || str_contains($titulo, 'e-mail')) {
                    $emailValor = $r['valor'] ?? null;
                    break;
                }
            }
            if ($emailValor) {
                $persona = Person::where('email', $emailValor)->first();
                if ($persona) {
                    ParticipanteEvento::where('idEvento', $id)->where('idPersona', $persona->id)->delete();
                }
            }
        }

        $respuesta->delete();

        return response()->json(['message' => 'Inscripción eliminada correctamente']);
    }

    private function normalizarTituloPregunta(string $titulo): string
    {
        return strtolower(
            preg_replace(
                '/[^a-z0-9 ]/i', '',
                iconv('UTF-8', 'ASCII//TRANSLIT', $titulo) ?: $titulo
            )
        );
    }

    /**
     * Registrar usuario externo (cross-sistema, sin auth de School).
     * Acepta email + nombre desde NexiService.
     */
    public function registerPublic(Request $request, $id)
    {
        $email  = trim($request->input('email', ''));
        $nombre = trim($request->input('nombre', 'Usuario'));

        if (!$email) {
            return response()->json(['message' => 'El email es requerido'], 422);
        }

        $tipoId   = DB::table('tipoIdentificacion')->value('id') ?? 1;
        $ciudadId = DB::table('ciudad')->value('id') ?? 1;

        $persona = Person::where('email', $email)->first();
        if (!$persona) {
            $parts   = explode(' ', $nombre);
            $persona = new Person();
            $persona->identificacion         = 'NEXI_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email) . '_' . time();
            $persona->nombre1                = $parts[0] ?? 'Usuario';
            $persona->nombre2                = $parts[1] ?? '';
            $persona->apellido1              = $parts[2] ?? 'NexiService';
            $persona->apellido2              = $parts[3] ?? '';
            $persona->fechaNac               = '1990-01-01';
            $persona->direccion              = 'N/A';
            $persona->email                  = $email;
            $persona->telefonoFijo           = '0000000';
            $persona->celular                = '0000000000';
            $persona->perfil                 = 'N/A';
            $persona->sexo                   = 'M';
            $persona->rh                     = 'O+';
            $persona->rutaFoto               = '/default/user.svg';
            $persona->idTipoIdentificacion   = $tipoId;
            $persona->idCiudad               = $ciudadId;
            $persona->idCiudadNac            = $ciudadId;
            $persona->idCiudadUbicacion      = $ciudadId;
            $persona->save();
        }

        ParticipanteEvento::updateOrCreate(
            ['idEvento' => $id, 'idPersona' => $persona->id],
            ['fechaRegistro' => now(), 'estado' => 'CONFIRMADO']
        );

        return response()->json(['message' => 'Inscripción confirmada', 'inscrito' => true]);
    }

    /**
     * Verificar inscripción de usuario externo por email (sin auth de School).
     * Si el evento tiene formulario interno, la fuente de verdad son las respuestas
     * del formulario: si fueron eliminadas, el usuario puede volver a inscribirse.
     */
    public function checkRegistrationPublic(Request $request, $id)
    {
        $email = trim($request->query('email', ''));
        if (!$email) {
            return response()->json(['inscrito' => false]);
        }

        $evento = Evento::find($id);
        if (!$evento) {
            return response()->json(['inscrito' => false]);
        }

        // Evento con formulario interno: la fuente de verdad son las respuestas del formulario
        if ($evento->idFormularioInterno) {
            $preguntasEmail = \App\Models\FormularioPregunta::where('idFormulario', $evento->idFormularioInterno)
                ->get()
                ->filter(function ($p) {
                    $t = $this->normalizarTituloPregunta($p->titulo);
                    return str_contains($t, 'correo') || str_contains($t, 'email') || str_contains($t, 'e-mail');
                })
                ->pluck('id')
                ->toArray();

            // Si el formulario tiene campo de email, verificar contra respuestas reales
            if (!empty($preguntasEmail)) {
                $emailLower = strtolower($email);
                $inscrito = \App\Models\FormularioRespuesta::where('idFormulario', $evento->idFormularioInterno)
                    ->get(['id', 'respuestas'])
                    ->contains(function ($r) use ($preguntasEmail, $emailLower) {
                        foreach ($r->respuestas ?? [] as $campo) {
                            if (
                                in_array($campo['idPregunta'] ?? null, $preguntasEmail) &&
                                strtolower(trim($campo['valor'] ?? '')) === $emailLower
                            ) {
                                return true;
                            }
                        }
                        return false;
                    });

                return response()->json(['inscrito' => $inscrito]);
            }
        }

        // Sin formulario (o sin campo email en el formulario): verificar ParticipanteEvento
        $persona = Person::where('email', $email)->first();
        if (!$persona) {
            return response()->json(['inscrito' => false]);
        }

        $inscrito = ParticipanteEvento::where('idEvento', $id)
            ->where('idPersona', $persona->id)
            ->exists();

        return response()->json(['inscrito' => $inscrito]);
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
