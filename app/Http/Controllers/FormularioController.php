<?php

namespace App\Http\Controllers;

use App\Models\Formulario;
use App\Models\FormularioPregunta;
use App\Models\FormularioOpcion;
use App\Models\FormularioRespuesta;
use App\Models\Evento;
use App\Models\ParticipanteEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\gestion_pago\PagoController;
use App\Models\AsignacionProcesoPago;
use App\Models\Factura;
use App\Models\Proceso;
use App\Models\TipoFactura;
use App\Util\KeyUtil;
use Carbon\Carbon;

class FormularioController extends Controller
{
    /**
     * Listar formularios de la empresa actual.
     */
    public function index(Request $request)
    {
        $idCompany = KeyUtil::idCompany();

        $formularios = Formulario::where('idCompany', $idCompany)
            ->withCount(['preguntas', 'respuestas'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($formularios);
    }

    /**
     * Crear un nuevo formulario con sus preguntas y opciones.
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'preguntas' => 'array'
        ]);

        try {
            DB::beginTransaction();

            $user = $request->user();
            
            $formulario = Formulario::create([
                'idCompany' => KeyUtil::idCompany(), // Obtener empresa del usuario
                'idUser' => $user->id,
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'colorTema' => $request->colorTema ?? '#6366f1',
                'estado' => $request->estado ?? 'borrador',
                'requiereAutenticacion' => $request->requiereAutenticacion ?? false,
            ]);

            if ($request->has('preguntas')) {
                foreach ($request->preguntas as $p) {
                    $pregunta = $formulario->preguntas()->create([
                        'tipo' => $p['tipo'],
                        'titulo' => $p['titulo'],
                        'descripcion' => $p['descripcion'] ?? null,
                        'esObligatoria' => $p['esObligatoria'] ?? false,
                        'orden' => $p['orden'],
                        'configuracion' => $p['configuracion'] ?? null,
                    ]);

                    if (isset($p['opciones']) && is_array($p['opciones'])) {
                        foreach ($p['opciones'] as $o) {
                            $pregunta->opciones()->create([
                                'texto' => $o['texto'],
                                'orden' => $o['orden'],
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json($formulario->load('preguntas.opciones'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un formulario específico con preguntas y opciones.
     */
    public function show($id)
    {
        $formulario = Formulario::with('preguntas.opciones')->findOrFail($id);
        return response()->json($formulario);
    }

    /**
     * Actualizar un formulario existente.
     */
    public function update(Request $request, $id)
    {
        $formulario = Formulario::findOrFail($id);

        try {
            DB::beginTransaction();

            $newSlug = $formulario->slug;
            if ($formulario->titulo !== $request->titulo) {
                $oldSlug = $formulario->slug;
                $parts = explode('-', $oldSlug);
                $suffix = end($parts);
                if (strlen($suffix) === 13) {
                    $newSlug = \Illuminate\Support\Str::slug($request->titulo) . '-' . $suffix;
                } else {
                    $newSlug = \Illuminate\Support\Str::slug($request->titulo) . '-' . uniqid();
                }
            }

            $formulario->update([
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'colorTema' => $request->colorTema,
                'estado' => $request->estado,
                'requiereAutenticacion' => $request->requiereAutenticacion,
                'slug' => $newSlug,
            ]);

            if ($request->has('preguntas')) {
                // Sincronizar preguntas
                $preguntasActualesIds = [];

                foreach ($request->preguntas as $p) {
                    if (isset($p['id']) && strpos((string)$p['id'], 'new') === false) {
                        // Actualizar pregunta existente
                        $pregunta = FormularioPregunta::find($p['id']);
                        if ($pregunta) {
                            $pregunta->update([
                                'tipo' => $p['tipo'],
                                'titulo' => $p['titulo'],
                                'descripcion' => $p['descripcion'] ?? null,
                                'esObligatoria' => $p['esObligatoria'] ?? false,
                                'orden' => $p['orden'],
                                'configuracion' => $p['configuracion'] ?? null,
                            ]);
                            $preguntasActualesIds[] = $pregunta->id;
                            
                            // Sincronizar opciones
                            if (isset($p['opciones']) && in_array($p['tipo'], ['opcion_multiple', 'casillas', 'desplegable'])) {
                                $opcionesActualesIds = [];
                                foreach ($p['opciones'] as $o) {
                                    if (isset($o['id']) && strpos((string)$o['id'], 'new') === false) {
                                        $opcion = FormularioOpcion::find($o['id']);
                                        if ($opcion) {
                                            $opcion->update(['texto' => $o['texto'], 'orden' => $o['orden']]);
                                            $opcionesActualesIds[] = $opcion->id;
                                        }
                                    } else {
                                        $nuevaOpcion = $pregunta->opciones()->create(['texto' => $o['texto'], 'orden' => $o['orden']]);
                                        $opcionesActualesIds[] = $nuevaOpcion->id;
                                    }
                                }
                                // Eliminar opciones que ya no están
                                FormularioOpcion::where('idFormularioPregunta', $pregunta->id)
                                    ->whereNotIn('id', $opcionesActualesIds)
                                    ->delete();
                            }
                        }
                    } else {
                        // Crear nueva pregunta
                        $pregunta = $formulario->preguntas()->create([
                            'tipo' => $p['tipo'],
                            'titulo' => $p['titulo'],
                            'descripcion' => $p['descripcion'] ?? null,
                            'esObligatoria' => $p['esObligatoria'] ?? false,
                            'orden' => $p['orden'],
                            'configuracion' => $p['configuracion'] ?? null,
                        ]);
                        $preguntasActualesIds[] = $pregunta->id;

                        if (isset($p['opciones']) && is_array($p['opciones'])) {
                            foreach ($p['opciones'] as $o) {
                                $pregunta->opciones()->create([
                                    'texto' => $o['texto'],
                                    'orden' => $o['orden'],
                                ]);
                            }
                        }
                    }
                }

                // Eliminar preguntas que ya no están
                FormularioPregunta::where('idFormulario', $formulario->id)
                    ->whereNotIn('id', $preguntasActualesIds)
                    ->delete();
            }

            DB::commit();

            return response()->json($formulario->load('preguntas.opciones'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar formulario.
     */
    public function destroy($id)
    {
        $formulario = Formulario::findOrFail($id);
        
        // Desvincular formulario de los eventos asociados
        Evento::where('idFormularioInterno', $id)->update([
            'idFormularioInterno' => null,
            'formUrl' => null,
            'formProvider' => null
        ]);

        $formulario->delete();
        return response()->json(['message' => 'Formulario eliminado.']);
    }

    /**
     * Ver formulario público por slug o ID.
     */
    public function showPublic($slugOrId)
    {
        $formulario = Formulario::with('preguntas.opciones')
            ->where('slug', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->firstOrFail();
        
        $isAuth = Auth::check() || Auth::guard('api')->check();
        $user = Auth::user() ?: Auth::guard('api')->user();
        $isEmbed = request()->query('embed') === 'true';

        if ($formulario->estado !== 'publicado' && !$isAuth && !$isEmbed) {
            return response()->json(['error' => 'Formulario no disponible'], 403);
        }

        $ultimaRespuesta = null;
        if ($isEmbed) {
            $emailParam = strtolower(trim(request()->query('email', '')));
            if ($emailParam) {
                $ultimaRespuesta = FormularioRespuesta::where('idFormulario', $formulario->id)
                    ->where('nexiEmail', $emailParam)
                    ->orderBy('id', 'desc')
                    ->first();
            }
        } elseif ($user) {
            $ultimaRespuesta = FormularioRespuesta::where('idFormulario', $formulario->id)
                ->where('idUser', $user->id)
                ->orderBy('id', 'desc')
                ->first();
        }

        $formulario->setAttribute('ultima_respuesta', $ultimaRespuesta);

        return response()->json($formulario);
    }

    /**
     * Guardar respuestas del formulario.
     */
    public function responder(Request $request, $slugOrId)
    {
        $formulario = Formulario::where('slug', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->firstOrFail();

        $isAuth = Auth::check() || Auth::guard('api')->check();
        $user = Auth::user() ?: Auth::guard('api')->user();
        $isEmbed = $request->boolean('embed') || $request->query('embed') === 'true';

        if ($formulario->estado !== 'publicado' && !$isAuth && !$isEmbed) {
            return response()->json(['error' => 'Formulario no disponible'], 403);
        }

        if ($formulario->requiereAutenticacion && !$isAuth) {
            return response()->json(['error' => 'Debe iniciar sesión para responder'], 401);
        }

        // Embed: identidad por nexiEmail; School nativo: por userId
        // Lee email desde query param O desde el cuerpo (doble vía para garantizar que llegue)
        $emailParam = strtolower(trim(
            request()->query('email', '')
            ?: $request->input('nexiEmail', '')
        ));
        $userId = $isEmbed ? null : ($user ? $user->id : null);

        try {
            DB::beginTransaction();

            // ── Fase 1: mismo login ───────────────────────────────────────
            // Busca directamente por nexiEmail (embed) o userId (School nativo).
            $existingByLogin = null;
            if ($isEmbed && $emailParam) {
                $existingByLogin = FormularioRespuesta::where('idFormulario', $formulario->id)
                    ->where('nexiEmail', $emailParam)
                    ->first();
            } elseif (!$isEmbed && $userId) {
                $existingByLogin = FormularioRespuesta::where('idFormulario', $formulario->id)
                    ->where('idUser', $userId)
                    ->first();
            }

            if ($existingByLogin && !$request->input('forzar_actualizacion', false)) {
                DB::rollBack();
                return response()->json([
                    'ya_inscrito'        => true,
                    'mismo_login'        => true,
                    'mensaje'            => 'Ya tienes una inscripción registrada. Puedes editarla si deseas actualizar tus datos.',
                    'respuesta_anterior' => $existingByLogin->respuestas,
                    'registrado_en'      => $existingByLogin->created_at,
                    'editado_en'         => $existingByLogin->updated_at,
                ], 409);
            }

            // ── Fase 2: conflicto de otro login (solo si no hay match propio) ──
            if (!$existingByLogin) {
                $studentDocNum = '';
                $studentEmail  = '';
                foreach ($request->respuestas ?? [] as $resp) {
                    $preg = \App\Models\FormularioPregunta::find($resp['idPregunta'] ?? 0);
                    if (!$preg) continue;
                    $val = $resp['valor'] ?? '';
                    if (is_array($val)) $val = implode(', ', $val);
                    $t = $this->normalizarTituloPregunta($preg->titulo);
                    if (str_contains($t, 'tutor') || str_contains($t, 'acudiente')) continue;
                    if (str_contains($t, 'numero') || (str_contains($t, 'documento') && !str_contains($t, 'tipo')) || str_contains($t, 'identificacion')) {
                        $studentDocNum = trim($val);
                    } elseif (str_contains($t, 'correo') || str_contains($t, 'email') || str_contains($t, 'e-mail')) {
                        $studentEmail = trim($val);
                    }
                }

                $conflictMatch = null;
                $conflictByDoc = false;
                if (!empty($studentDocNum) || !empty($studentEmail)) {
                    foreach (FormularioRespuesta::where('idFormulario', $formulario->id)->get() as $existing) {
                        $listaResp = $existing->respuestas;
                        if (!is_array($listaResp)) continue;
                        $hasDoc = $hasEmail = false;
                        foreach ($listaResp as $r) {
                            $preg = \App\Models\FormularioPregunta::find($r['idPregunta'] ?? 0);
                            if (!$preg) continue;
                            $v = trim(is_array($r['valor']) ? implode(', ', $r['valor']) : ($r['valor'] ?? ''));
                            $t = $this->normalizarTituloPregunta($preg->titulo);
                            if (str_contains($t, 'tutor') || str_contains($t, 'acudiente')) continue;
                            if (!empty($studentDocNum) && (str_contains($t, 'numero') || (str_contains($t, 'documento') && !str_contains($t, 'tipo')) || str_contains($t, 'identificacion'))) {
                                if ($v === $studentDocNum) $hasDoc = true;
                            }
                            if (!empty($studentEmail) && (str_contains($t, 'correo') || str_contains($t, 'email') || str_contains($t, 'e-mail'))) {
                                if (strcasecmp($v, $studentEmail) === 0) $hasEmail = true;
                            }
                        }
                        // Email match = priority; para en cuanto lo encuentra
                        if ($hasEmail) { $conflictMatch = $existing; $conflictByDoc = false; break; }
                        if ($hasDoc && !$conflictMatch) { $conflictMatch = $existing; $conflictByDoc = true; }
                    }
                }

                if ($conflictMatch) {
                    DB::rollBack();
                    return response()->json([
                        'ya_inscrito'        => true,
                        'mismo_login'        => false,
                        'solo_documento'     => $conflictByDoc,
                        'mensaje'            => $conflictByDoc
                            ? 'El número de documento ya está registrado por otra persona.'
                            : 'El correo electrónico ya está registrado por otra persona.',
                        'respuesta_anterior' => null,
                        'registrado_en'      => null,
                        'editado_en'         => null,
                    ], 409);
                }
            }

            // ── Crear o actualizar ────────────────────────────────────────
            if ($existingByLogin) {
                $existingByLogin->update(['respuestas' => $request->respuestas ?? []]);
                $respuesta = $existingByLogin;
            } else {
                $respuesta = FormularioRespuesta::create([
                    'idFormulario' => $formulario->id,
                    'idUser'       => $userId,
                    'ipAddress'    => $request->ip(),
                    'nexiEmail'    => ($isEmbed && $emailParam) ? $emailParam : null,
                    'respuestas'   => $request->respuestas ?? [],
                ]);
            }

            // Auto-inscribir a evento si está asociado
            $evento = Evento::where('idFormularioInterno', $formulario->id)->first();
            if ($evento && $user) {
                if ($user->idpersona) {
                    // Buscamos si el usuario ya es participante
                    $participante = ParticipanteEvento::where('idEvento', $evento->idEvento)
                        ->where('idPersona', $user->idpersona)
                        ->first();
                    
                    if (!$participante) {
                        $participante = ParticipanteEvento::create([
                            'idEvento' => $evento->idEvento,
                            'idPersona' => $user->idpersona,
                            'estado' => 'CONFIRMADO' // Ajustar al valor real
                        ]);
                    } else {
                        // Actualizamos estado a CONFIRMADO
                        $participante->update([
                            'estado' => 'CONFIRMADO' 
                        ]);
                    }
                }
            }

            // Si es el formulario de inscripción pública de estudiantes o el configurado para la empresa, generar registros académicos y factura para validar
            $isEnrollmentForm = false;
            $companyConfig = \App\Models\Company::find($formulario->idCompany);
            if ($companyConfig && $companyConfig->idFormularioInscripcion == $formulario->id) {
                $isEnrollmentForm = true;
            }

            $facturaInscripcion = null;
            $advertenciaFactura = null;

            if ($formulario->slug === 'inscripcion-estudiantes' || $isEnrollmentForm) {
                $studentName = '';
                $studentDocType = 'CC';
                $studentDocNum = '';
                $studentBirthDate = null;
                $studentEmail = '';
                $studentPhone = '';
                $studentPhoneSec = '';
                $programName = '';
                $tutorName = '';
                $tutorParentesco = '';
                $tutorDocNum = '';
                $tutorPhone = '';
                $tutorEmail = '';

                foreach ($respuesta->respuestas as $resp) {
                    $preg = \App\Models\FormularioPregunta::find($resp['idPregunta'] ?? 0);
                    if (!$preg) continue;
                    $val = $resp['valor'] ?? '';
                    if (is_array($val)) $val = implode(', ', $val);
                    $titleLower = $this->normalizarTituloPregunta($preg->titulo);

                    // Student name
                    if ((str_contains($titleLower, 'nombre') && (str_contains($titleLower, 'estudiante') || str_contains($titleLower, 'aspirante') || str_contains($titleLower, 'participante') || str_contains($titleLower, 'alumno'))) || str_contains($titleLower, 'nombre completo') || str_contains($titleLower, 'nombres y apellidos')) {
                        if (!str_contains($titleLower, 'tutor') && !str_contains($titleLower, 'acudiente')) {
                            $studentName = $val;
                        }
                    }
                    // Student doc type
                    elseif (str_contains($titleLower, 'tipo') && str_contains($titleLower, 'documento')) {
                        if (!str_contains($titleLower, 'tutor') && !str_contains($titleLower, 'acudiente')) {
                            $studentDocType = $val;
                        }
                    }
                    // Student doc num
                    elseif (str_contains($titleLower, 'número') || str_contains($titleLower, 'numero') || str_contains($titleLower, 'documento') || str_contains($titleLower, 'identificación') || str_contains($titleLower, 'identificacion')) {
                        if (!str_contains($titleLower, 'tutor') && !str_contains($titleLower, 'acudiente')) {
                            $studentDocNum = $val;
                        }
                    }
                    // Student birth date
                    elseif (str_contains($titleLower, 'fecha') && str_contains($titleLower, 'nacimiento')) {
                        $studentBirthDate = $val;
                    }
                    // Student email
                    elseif (str_contains($titleLower, 'correo') || str_contains($titleLower, 'email') || str_contains($titleLower, 'e-mail')) {
                        if (!str_contains($titleLower, 'tutor') && !str_contains($titleLower, 'acudiente')) {
                            $studentEmail = $val;
                        }
                    }
                    // Student phone
                    elseif (str_contains($titleLower, 'telefono') || str_contains($titleLower, 'celular') || str_contains($titleLower, 'movil') || (str_contains($titleLower, 'tel') && !str_contains($titleLower, 'satelite'))) {
                        if (!str_contains($titleLower, 'tutor') && !str_contains($titleLower, 'acudiente')) {
                            if (str_contains($titleLower, 'secundario')) {
                                $studentPhoneSec = $val;
                            } else {
                                $studentPhone = $val;
                            }
                        }
                    }
                    // Program interest
                    elseif (str_contains($titleLower, 'programa') || str_contains($titleLower, 'curso') || str_contains($titleLower, 'carrera') || str_contains($titleLower, 'interés') || str_contains($titleLower, 'interes')) {
                        $programName = $val;
                    }
                    // Tutor name
                    elseif (str_contains($titleLower, 'nombre') && (str_contains($titleLower, 'tutor') || str_contains($titleLower, 'acudiente'))) {
                        $tutorName = $val;
                    }
                    // Tutor relationship
                    elseif (str_contains($titleLower, 'parentesco') || str_contains($titleLower, 'relación') || str_contains($titleLower, 'relacion')) {
                        $tutorParentesco = $val;
                    }
                    // Tutor doc num
                    elseif ((str_contains($titleLower, 'documento') || str_contains($titleLower, 'identificación') || str_contains($titleLower, 'identificacion') || str_contains($titleLower, 'cédula') || str_contains($titleLower, 'cedula')) && (str_contains($titleLower, 'tutor') || str_contains($titleLower, 'acudiente'))) {
                        $tutorDocNum = $val;
                    }
                    // Tutor phone
                    elseif ((str_contains($titleLower, 'teléfono') || str_contains($titleLower, 'telefono') || str_contains($titleLower, 'celular') || str_contains($titleLower, 'móvil') || str_contains($titleLower, 'movil')) && (str_contains($titleLower, 'tutor') || str_contains($titleLower, 'acudiente'))) {
                        $tutorPhone = $val;
                    }
                    // Tutor email
                    elseif ((str_contains($titleLower, 'correo') || str_contains($titleLower, 'email') || str_contains($titleLower, 'e-mail')) && (str_contains($titleLower, 'tutor') || str_contains($titleLower, 'acudiente'))) {
                        $tutorEmail = $val;
                    }
                }

                // Generar partes del nombre del estudiante
                $parts = explode(' ', preg_replace('/\s+/', ' ', trim($studentName)));
                $nombre1 = $parts[0] ?? '';
                $nombre2 = count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : '';
                $apellido1 = count($parts) > 1 ? end($parts) : '';
                $apellido2 = '';

                // 1. Crear o actualizar Tercero del estudiante
                $terceroEstudiante = \App\Models\Tercero::updateOrCreate(
                    ['identificacion' => $studentDocNum ?: '0', 'idCompany' => $formulario->idCompany],
                    [
                        'nombre' => $studentName ?: 'Aspirante Inscrito',
                        'email' => $studentEmail ?: '',
                        'telefono' => $studentPhone ?: '',
                        'idCompany' => $formulario->idCompany
                    ]
                );

                $tipoId = \DB::table('tipoIdentificacion')->value('id') ?? 1;

                // 2. Crear o actualizar Person del estudiante
                $personEstudiante = \App\Models\Person::updateOrCreate(
                    ['identificacion' => $studentDocNum ?: '0'],
                    [
                        'nombre1' => $nombre1 ?: 'Aspirante',
                        'nombre2' => $nombre2 ?: '',
                        'apellido1' => $apellido1 ?: 'Inscrito',
                        'apellido2' => $apellido2 ?: '',
                        'email' => $studentEmail ?: '',
                        'celular' => $studentPhone ?: '',
                        'fechaNac' => $studentBirthDate ?: '2000-01-01',
                        'direccion' => 'Desconocida',
                        'sexo' => 'M',
                        'perfil' => '',
                        'idTipoIdentificacion' => $tipoId,
                    ]
                );

                // 3. Crear Tutor/Acudiente si es menor de edad
                $idAcudiente = null;
                $isMenorEdad = false;
                if ($studentBirthDate) {
                    $birth = \Carbon\Carbon::parse($studentBirthDate);
                    if ($birth->age < 18) {
                        $isMenorEdad = true;
                    }
                }
                
                // Also check if they answered "Sí" to a "menor de edad" question
                foreach ($respuesta->respuestas as $resp) {
                    $preg = \App\Models\FormularioPregunta::find($resp['idPregunta'] ?? 0);
                    if (!$preg) continue;
                    $titleLower = $this->normalizarTituloPregunta($preg->titulo);
                    if (str_contains($titleLower, 'menor de edad') || str_contains($titleLower, 'menor de 18')) {
                        $val = mb_strtolower(trim($resp['valor'] ?? ''));
                        if ($val === 'sí' || $val === 'si' || $val === 'yes') {
                            $isMenorEdad = true;
                        }
                    }
                }

                if ($isMenorEdad && !empty($tutorDocNum)) {
                    $tutorTercero = \App\Models\Tercero::updateOrCreate(
                            ['identificacion' => $tutorDocNum, 'idCompany' => $formulario->idCompany],
                            [
                                'nombre' => $tutorName ?: 'Tutor Acudiente',
                                'email' => $tutorEmail ?: '',
                                'telefono' => $tutorPhone ?: '',
                                'idCompany' => $formulario->idCompany
                            ]
                        );

                        $tParts = explode(' ', preg_replace('/\s+/', ' ', trim($tutorName)));
                        $tNombre1 = $tParts[0] ?? '';
                        $tNombre2 = count($tParts) > 2 ? implode(' ', array_slice($tParts, 1, -1)) : '';
                        $tApellido1 = count($tParts) > 1 ? end($tParts) : '';

                        $tPerson = \App\Models\Person::updateOrCreate(
                            ['identificacion' => $tutorDocNum],
                            [
                                'nombre1' => $tNombre1 ?: 'Tutor',
                                'nombre2' => $tNombre2 ?: '',
                                'apellido1' => $tApellido1 ?: 'Acudiente',
                                'email' => $tutorEmail ?: '',
                                'celular' => $tutorPhone ?: '',
                                'fechaNac' => '2000-01-01',
                                'direccion' => 'Desconocida',
                                'sexo' => 'M',
                                'perfil' => '',
                                'idTipoIdentificacion' => $tipoId,
                            ]
                        );
                        $idAcudiente = $tPerson->id;
                    }

                // Find a matching Grado or default to 1
                $gradoId = 1;
                if (!empty($programName)) {
                    $matchedGrado = \App\Models\Grado::where('nombreGrado', 'like', "%{$programName}%")->first();
                    if ($matchedGrado) {
                        $gradoId = $matchedGrado->id;
                    }
                }

                $fichaId = \App\Models\Ficha::value('id') ?? 1;

                // 4. Crear o actualizar matrícula en estado INSCRIPCION
                $observacionMatricula = 'FormResponseID:' . $respuesta->id;
                $matricula = \App\Models\Matricula::where('observacion', $observacionMatricula)
                    ->where('idCompany', $formulario->idCompany)
                    ->first();

                if ($matricula) {
                    $matricula->update([
                        'idPersona' => $personEstudiante->id,
                        'idAcudiente' => $idAcudiente,
                        'idGrado' => $gradoId,
                        'observacion' => $observacionMatricula,
                    ]);
                } else {
                    $matricula = \App\Models\Matricula::create([
                        'idPersona' => $personEstudiante->id,
                        'idAcudiente' => $idAcudiente,
                        'estado' => 'INSCRIPCION',
                        'idCompany' => $formulario->idCompany,
                        'fecha' => \Carbon\Carbon::now(),
                        'idGrado' => $gradoId,
                        'idFicha' => $fichaId,
                        'observacion' => $observacionMatricula,
                    ]);
                }

                // 5. Generar factura individual (idempotente) con valores económicos del proceso
                $proceso = $this->resolverProcesoInscripcionFormulario($programName, (int) $formulario->idCompany);

                if ($proceso) {
                    /** @var PagoController $pagoController */
                    $pagoController = app(PagoController::class);
                    $resultadoFactura = $pagoController->crearFacturaIndividualInscripcion(
                        (int) $proceso->id,
                        (int) $formulario->idCompany,
                        (int) $terceroEstudiante->id
                    );

                    if (!empty($resultadoFactura['factura'])) {
                        $facturaInscripcion = $resultadoFactura['factura'];
                        // Store direct link from Factura → FormularioRespuesta
                        if (!$facturaInscripcion->idFormularioRespuesta) {
                            $facturaInscripcion->idFormularioRespuesta = $respuesta->id;
                            $facturaInscripcion->save();
                        }
                    }
                    if (!empty($resultadoFactura['error'])) {
                        $advertenciaFactura = $resultadoFactura['error'];
                        Log::warning('Inscripción: no se generó factura automática', [
                            'idTercero' => $terceroEstudiante->id,
                            'idProceso' => $proceso->id,
                            'programName' => $programName,
                            'error' => $resultadoFactura['error'],
                        ]);
                    }
                } else {
                    $advertenciaFactura = 'No se identificó el proceso académico. La solicitud quedó registrada y puede gestionarse manualmente.';
                    Log::warning('Inscripción sin proceso resuelto para factura', [
                        'idTercero' => $terceroEstudiante->id,
                        'programName' => $programName,
                        'idCompany' => $formulario->idCompany,
                    ]);
                    // Crear factura placeholder para que aparezca en solicitudes pendientes
                    $facturaExistente = Factura::where('idTercero', $terceroEstudiante->id)
                        ->where('idCompany', $formulario->idCompany)
                        ->where('idTipoFactura', TipoFactura::VENTA)
                        ->whereNotNull('idFormularioRespuesta')
                        ->where('idFormularioRespuesta', $respuesta->id)
                        ->first();
                    if (!$facturaExistente) {
                        $lastFactura = Factura::where('idTipoFactura', TipoFactura::VENTA)->orderBy('id', 'desc')->first();
                        $numeroNuevo = $lastFactura
                            ? str_pad((int) $lastFactura->numeroFactura + 1, 5, '0', STR_PAD_LEFT)
                            : '00001';
                        $facturaInscripcion = new Factura();
                        $facturaInscripcion->numeroFactura = $numeroNuevo;
                        $facturaInscripcion->fecha = Carbon::now();
                        $facturaInscripcion->valor = 0;
                        $facturaInscripcion->idTercero = $terceroEstudiante->id;
                        $facturaInscripcion->idCompany = $formulario->idCompany;
                        $facturaInscripcion->idTipoFactura = TipoFactura::VENTA;
                        $facturaInscripcion->idFormularioRespuesta = $respuesta->id;
                        $facturaInscripcion->save();
                    }
                }
            }

            DB::commit();

            $responsePayload = [
                'message' => 'Respuesta guardada con éxito',
                'data' => $respuesta,
            ];

            if (isset($facturaInscripcion) && $facturaInscripcion) {
                $responsePayload['idFactura'] = $facturaInscripcion->id;
                $responsePayload['numeroFactura'] = $facturaInscripcion->numeroFactura;
            }
            if (isset($advertenciaFactura) && $advertenciaFactura) {
                $responsePayload['advertenciaFactura'] = $advertenciaFactura;
            }

            return response()->json($responsePayload);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener respuestas de un formulario (para el builder).
     */
    public function respuestas($id)
    {
        $respuestas = FormularioRespuesta::where('idFormulario', $id)
            ->with(['usuario' => function($query) {
                $query->with('persona:id,nombre1,nombre2,apellido1,apellido2');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($r) {
                if ($r->usuario) {
                    $persona = $r->usuario->persona;
                    $parts = array_filter([
                        $persona->nombre1 ?? '',
                        $persona->nombre2 ?? '',
                        $persona->apellido1 ?? '',
                        $persona->apellido2 ?? '',
                    ]);
                    $r->usuario->name = implode(' ', $parts) ?: $r->usuario->email;
                } elseif ($r->nexiEmail) {
                    $r->usuario = (object) [
                        'name'       => $r->nexiEmail,
                        'email'      => $r->nexiEmail,
                        'persona'    => null,
                        'isNexiUser' => true,
                    ];
                }
                return $r;
            });

        return response()->json($respuestas);
    }

    /**
     * Eliminar una respuesta específica de un formulario.
     */
    public function destroyRespuesta($formularioId, $respuestaId)
    {
        $respuesta = FormularioRespuesta::where('idFormulario', $formularioId)
            ->where('id', $respuestaId)
            ->firstOrFail();

        $respuesta->delete();

        return response()->json(['message' => 'Respuesta eliminada correctamente']);
    }

    /**
     * Subir archivo adjunto públicamente para formularios.
     */
    public function uploadAdjunto(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,jpeg,png,jpg,doc,docx|max:20480',
        ]);

        try {
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $path = $file->store('formularios/adjuntos', 'public');
                $url = asset('storage/' . $path);

                return response()->json([
                    'success' => true,
                    'url' => $url,
                    'path' => $path
                ]);
            }
            return response()->json(['error' => 'No se recibió ningún archivo'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    private function normalizarTituloPregunta(string $titulo): string
    {
        $t = mb_strtolower(trim($titulo));
        $t = preg_replace('/\?+/u', '', $t) ?? $t;
        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'ü' => 'u',
        ];

        return preg_replace('/\s+/', ' ', strtr($t, $reemplazos)) ?? $t;
    }


    private function resolverProcesoInscripcionFormulario(?string $programName, int $idCompany): ?Proceso
    {
        $programName = trim((string) $programName);
        if ($programName !== '') {
            $proceso = Proceso::where('nombreProceso', $programName)->first();
            if (!$proceso) {
                $proceso = Proceso::where('nombreProceso', 'like', '%' . $programName . '%')->first();
            }
            if ($proceso) {
                return $proceso;
            }
        }

        $conteoPorProceso = AsignacionProcesoPago::query()
            ->whereHas('configuracionPago', function ($query) use ($idCompany) {
                $query->where('idCompany', $idCompany)
                    ->where('estado', 'ACTIVO');
            })
            ->selectRaw('idProceso, COUNT(*) as total')
            ->groupBy('idProceso')
            ->orderByDesc('total')
            ->get();

        if ($conteoPorProceso->isEmpty()) {
            return null;
        }

        $procesoPreferido = $conteoPorProceso->first(function ($row) {
            $nombre = mb_strtolower((string) Proceso::where('id', $row->idProceso)->value('nombreProceso'));

            return str_contains($nombre, 'matricula') || str_contains($nombre, 'inscripcion');
        });

        $idProceso = $procesoPreferido
            ? (int) $procesoPreferido->idProceso
            : (int) $conteoPorProceso->first()->idProceso;

        return Proceso::find($idProceso);
    }
}

