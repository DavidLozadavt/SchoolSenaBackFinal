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
use App\Util\KeyUtil;

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
        if ($formulario->estado !== 'publicado' && !$isAuth) {
            return response()->json(['error' => 'Formulario no disponible'], 403);
        }

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

        if ($formulario->estado !== 'publicado' && !$isAuth) {
            return response()->json(['error' => 'Formulario no disponible'], 403);
        }

        if ($formulario->requiereAutenticacion && !$isAuth) {
            return response()->json(['error' => 'Debe iniciar sesión para responder'], 401);
        }

        $userId = $user ? $user->id : null;

        // Validar si ya respondió (si requiere auth y el formulario está publicado)
        if ($formulario->estado === 'publicado' && $formulario->requiereAutenticacion && FormularioRespuesta::where('idFormulario', $formulario->id)->where('idUser', $userId)->exists()) {
            return response()->json(['error' => 'Ya has respondido este formulario'], 403);
        }

        try {
            DB::beginTransaction();

            $respuesta = FormularioRespuesta::create([
                'idFormulario' => $formulario->id,
                'idUser' => $userId,
                'ipAddress' => $request->ip(),
                'respuestas' => $request->respuestas ?? [],
            ]);

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
                    $titleLower = mb_strtolower(trim($preg->titulo));

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
                    elseif (str_contains($titleLower, 'teléfono') || str_contains($titleLower, 'telefono') || str_contains($titleLower, 'celular') || str_contains($titleLower, 'móvil') || str_contains($titleLower, 'movil')) {
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
                    $titleLower = mb_strtolower(trim($preg->titulo));
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

                // 4. Crear Matrícula en estado INSCRIPCION
                $matricula = \App\Models\Matricula::create([
                    'idPersona' => $personEstudiante->id,
                    'idAcudiente' => $idAcudiente,
                    'estado' => 'INSCRIPCION',
                    'idCompany' => $formulario->idCompany,
                    'fecha' => \Carbon\Carbon::now(),
                    'idGrado' => $gradoId,
                    'idFicha' => $fichaId,
                    'observacion' => 'FormResponseID:' . $respuesta->id,
                ]);

                // Buscar proceso (Programa de Interés) y Configuración de Pago asociada
                $proceso = \App\Models\Proceso::where('nombreProceso', $programName)->first();
                $idConfigPago = null;
                if ($proceso) {
                    $asignacion = \App\Models\AsignacionProcesoPago::where('idProceso', $proceso->id)->first();
                    if ($asignacion) {
                        $idConfigPago = $asignacion->idConfiguracionPago;
                    }
                }

                if (!$idConfigPago) {
                    $configPago = \App\Models\ConfiguracionPago::where('idCompany', $formulario->idCompany)->first();
                    if (!$configPago) {
                        $configPago = \App\Models\ConfiguracionPago::create([
                            'titulo' => 'Inscripción Estándar',
                            'detalle' => 'Derechos de inscripción y matrícula',
                            'valor' => 0,
                            'estado' => 'ACTIVO',
                            'idCompany' => $formulario->idCompany
                        ]);
                    }
                    $idConfigPago = $configPago?->id;
                }

                // 5. Generar Factura académica (solicitud)
                $factura = new \App\Models\Factura();
                $lastFactura = \App\Models\Factura::where('idTipoFactura', \App\Models\TipoFactura::VENTA)->orderBy('id', 'desc')->first();
                $factura->numeroFactura = $lastFactura
                    ? str_pad((int) $lastFactura->numeroFactura + 1, 5, '0', STR_PAD_LEFT)
                    : '00001';
                $factura->fecha = \Carbon\Carbon::now();
                $factura->valor = 0;
                $factura->valorIva = 0;
                $factura->valorMasIva = 0;
                $factura->idTercero = $terceroEstudiante->id;
                $factura->idCompany = $formulario->idCompany;
                $factura->idTipoFactura = \App\Models\TipoFactura::VENTA;
                $factura->save();

                // Detalle Factura
                $detalleFactura = new \App\Models\DetalleFactura();
                $detalleFactura->idFactura = $factura->id;
                
                $configPago = \App\Models\ConfiguracionPago::find($idConfigPago);
                $detalleFactura->detalle = $configPago ? $configPago->titulo : ($programName ?: 'Proceso académico');
                $detalleFactura->valor = 0;
                if (\Schema::hasColumn('detalleFactura', 'idConfiguracionPago')) {
                    $detalleFactura->idConfiguracionPago = $idConfigPago;
                }
                $detalleFactura->save();

                // Transacción pendiente
                $transaccion = new \App\Models\Transaccion();
                $transaccion->valor = 0;
                $transaccion->hora = \Carbon\Carbon::now()->format('H:i');
                $transaccion->fechaTransaccion = \Carbon\Carbon::now();
                $transaccion->idTipoTransaccion = \App\Models\TipoTransaccion::VENTA;
                $transaccion->idEstado = \App\Models\Status::ID_PENDIENTE;
                $transaccion->excedente = 0;
                $transaccion->save();

                $asignacionFacturaTransaccion = new \App\Models\AsignacionFacturaTransaccion();
                $asignacionFacturaTransaccion->idFactura = $factura->id;
                $asignacionFacturaTransaccion->idTransaccion = $transaccion->id;
                $asignacionFacturaTransaccion->save();

                $pago = new \App\Models\Pago();
                $pago->fechaPago = \Carbon\Carbon::now();
                $pago->fechaReg = \Carbon\Carbon::now();
                $pago->valor = 0;
                $pago->excedente = 0;
                $pago->idEstado = \App\Models\Status::ID_PENDIENTE;
                $pago->idTransaccion = $transaccion->id;
                $pago->save();
            }

            DB::commit();

            return response()->json(['message' => 'Respuesta guardada con éxito', 'data' => $respuesta]);
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
                }
                return $r;
            });

        return response()->json($respuestas);
    }

    /**
     * Subir archivo adjunto públicamente para formularios.
     */
    public function uploadAdjunto(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,jpeg,png,jpg,doc,docx|max:5120',
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
}

