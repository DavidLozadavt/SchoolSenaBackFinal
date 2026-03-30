<?php

namespace App\Http\Controllers\gestion_contratacion;

use Carbon\Carbon;
use App\Models\Rol;
use App\Models\Pago;
use App\Models\User;
use App\Util\KeyUtil;
use App\Models\Person;
use App\Models\Status;
use App\Models\Proceso;
use App\Models\Salario;
use App\Models\Tercero;
use App\Models\Contract;
use App\Models\TipoPago;
use App\Mail\MailService;
use App\Models\TipoTercero;
use App\Models\Transaccion;
use App\Models\ContractType;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use App\Models\TipoDocumento;
use App\Models\ArchivoContrato;
use App\Models\TipoTransaccion;
use App\Models\DocumentoContrato;
use Illuminate\Http\JsonResponse;
use App\Models\IdentificationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use App\Models\ContratoTransaccion;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\ActivationCompanyUser;
use App\Models\ActividadRiesgoProfesional;
use App\Models\AsignacionProcesoTipoDocumento;
use App\Models\Banco;
use App\Models\Nomina\Vacacion;
use App\Models\Novedad;
use App\Models\ObservacionPreocupacional;
use App\Models\TipoTerminacionContrato;
use App\Models\AreaConocimiento;
use App\Models\Programa;
use App\Models\NivelEducativo;
use App\Models\Company;
use App\Models\CentrosFormacion;
use App\Enums\TypePaymentMethodContract;

class ContratacionController extends Controller
{


    /**
     * Obtiene todos los tipos de identificación disponibles.
     *
     * Esta función consulta la base de datos para recuperar todos los tipos de identificación
     * almacenados en la tabla 'tipoIdentficacion' y los devuelve como una respuesta JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tiposIdentificacion()
    {
        $tiposIdentificacion = IdentificationType::all();
        return response()->json($tiposIdentificacion);
    }

    /**
     * Get all contracts actives by company
     * @return JsonResponse|mixed
     */
    public function getContractsActivesNominas(): JsonResponse
    {
        $contracts = Contract::where('idEstado', 1)
            ->where('idempresa', KeyUtil::idCompany())
            ->whereIn('idtipoContrato', [6, 7, 8, 9])
            ->with(['persona', 'salario', 'otrasDeducciones'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($contracts);
    }


    /**
     * Obtiene información detallada de una persona mediante su identificación.
     *
     * Esta función busca en la base de datos la información detallada de una persona
     * utilizando su identificación. Retorna un objeto JSON con los detalles de la persona
     * y, si existen, los contratos asociados
     *
     * @param  string  $identificacion  La identificación de la persona a buscar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPersonaByIdentificacion($identificacion)
    {
        $persona = Person::with(
            'ciudadNac.departamento',
            'ciudad.departamento',
            'ciudadUbicacion.departamento',
            'ciudadExpedicion.departamento',
            'tipoIdentificacion'
        )
            ->where('identificacion', '=', $identificacion)->first();


        if ($persona) {
            $contratos = Contract::where('idpersona', '=', $persona->id)->get();

            foreach ($contratos as $contrato) {
                if ($contrato->idEstado == Status::ID_ACTIVE) {
                    return response()->json(['error' => 'Esta persona aún tiene un contrato vigente.'], 400);
                }
            }

            $persona->contratos = $contratos;
        }

        return response()->json($persona);
    }



    /**
     * Obtiene todos los tipos de contrato disponibles.
     *
     * Esta función consulta la base de datos para recuperar todos los tipos de contrato
     * almacenados en la tabla 'tipoContrato' y los devuelve como una respuesta JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tiposContrato()
    {
        $tipoContratos = ContractType::all();
        return response()->json($tipoContratos);
    }



    /**
     * Obtiene los tipos de documento asociados a un proceso específico.
     *
     * Esta función toma el nombre de un proceso como entrada y devuelve los tipos de documento
     * asociados a ese proceso. Retorna un objeto JSON con la información.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function tipoDocumento(Request $request)
    {
        try {
            $nombreProceso = $request->input('nombreProceso');
            
            if (empty($nombreProceso)) {
                return response()->json(['error' => 'El nombre del proceso es requerido'], 400);
            }

            $proceso = Proceso::where('nombreProceso', $nombreProceso)->first();

            // Si no existe el proceso, intentar crearlo desde el tipo de contrato
            if (!$proceso) {
                // Buscar si existe un tipo de contrato con ese nombre
                $tipoContrato = ContractType::where('nombreTipoContrato', $nombreProceso)->first();
                
                if ($tipoContrato) {
                    // Crear el proceso automáticamente basado en el tipo de contrato
                    $proceso = new Proceso();
                    $proceso->nombreProceso = $tipoContrato->nombreTipoContrato;
                    $proceso->descripcion = $tipoContrato->descripcion ?: 'Proceso creado automáticamente desde tipo de contrato';
                    $proceso->save();
                } else {
                    // Si no existe ni proceso ni tipo de contrato, devolver 404
                    return response()->json(['error' => 'No se encontró el proceso especificado'], 404);
                }
            }

            $tipoDocumentos = AsignacionProcesoTipoDocumento::with('proceso', 'tipoDocumento')
                ->where('idProceso', $proceso->id)
                ->get();

            // Si no hay documentos asignados, devolver array vacío en lugar de error 404
            // Esto permite que el formulario continúe sin documentos requeridos
            return response()->json($tipoDocumentos);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Error al obtener los tipos de documento: ' . $th->getMessage()], 500);
        }
    }


    /**
     * Obtiene todos los roles con información de salario asociada.
     *
     * Esta función obtiene todos los roles de la empresa actual, cargando información
     * adicional sobre salario para cada rol. Retorna un objeto JSON con la información de los roles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoles()
    {
        $roles = Rol::query()
            ->with('salario')
            ->get();

        return response()->json($roles);
    }



    /**
     * Obtiene todos los contratos asociados a una persona por identificación.
     *
     * Esta función busca todos los contratos relacionados con una persona mediante su identificación.
     * Retorna un objeto JSON con la información de los contratos, incluyendo detalles de la persona y el tipo de contrato.
     *
     * @param  string  $identificacion  La identificación de la persona.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContratoByIdentificacion($identificacion)
    {
        if (empty($identificacion)) {
            return response()->json(['error' => 'La identificación es requerida'], 400);
        }

        $user = KeyUtil::user();
        $contratos = Contract::with('persona', 'tipoContrato')
            ->whereHas("persona", function ($q) use ($identificacion) {
                return $q->select('id')
                    ->where('identificacion', '=', $identificacion);
            })
            ->get();

        return response()->json($contratos);
    }


    public function getContratoByPersonaLogueada()
    {
        $idPersona = KeyUtil::user()->idpersona;

        $contrato = Contract::with(
            'persona',
            'tipoContrato',
            'documentosContrato.AsignacionTipoDocumentoProceso.tipoDocumento',
            'persona.ciudadUbicacion',
            'salario.rol',
            'tipoContrato',
            'empresa',
            'estado'
        )
            ->where('idpersona', $idPersona)
            ->get();

        if ($contrato) {
            return response()->json($contrato);
        } else {
            return response()->json(['message' => 'Contrato no encontrado'], 404);
        }
    }




    /**
     * Almacena o actualiza la información de una persona, incluyendo la creación de un usuario asociado.
     *
     * Esta función inicia una transacción de base de datos y guarda o actualiza la información de una persona
     * basándose en la dirección de correo electrónico proporcionada. También garantiza que exista un usuario
     * asociado con el mismo ID de persona. La contraseña del usuario se establece como la identificación de la persona.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePersona(Request $request)
    {
        try {
            $email = trim((string) $request->input('email', ''));
            $identificacion = trim((string) $request->input('identificacion', ''));

            if ($identificacion === '') {
                return response()->json([
                    'message' => 'La identificación es obligatoria para registrar o localizar la persona.',
                ], 422);
            }

            DB::beginTransaction();
            $company_id = KeyUtil::idCompany();

            // IMPORTANTE: no usar (email = X OR identificacion = Y) en una sola consulta.
            // Si el correo está vacío o repetido en pruebas, `where('email', '')` o el OR hacía que
            // siempre se devolviera la misma persona (ej. id 858) aunque la cédula fuera otra.
            // La persona natural se identifica por documento; el correo puede repetirse o quedar mal cargado.
            $personaExistente = Person::where('identificacion', $identificacion)->first();

            if ($personaExistente) {
                // La tabla `usuario` usa la columna `idpersona` (minúsculas). Debe existir usuario
                // para poder crear el contrato; si la persona vino de otro flujo sin usuario, lo creamos.
                $userExistente = User::where('idpersona', $personaExistente->id)->first();
                if (!$userExistente) {
                    if (User::where('id', $personaExistente->id)->exists()) {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'Hay un conflicto entre el usuario y la persona en el sistema. Contacte a soporte.',
                        ], 409);
                    }
                    $correoPersona = trim((string) ($personaExistente->email ?? ''));
                    if ($correoPersona === '') {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'La persona no tiene correo en el sistema. Actualiza los datos de la persona con un correo válido antes de contratar.',
                        ], 422);
                    }
                    $correoEnUso = User::where('email', $correoPersona)
                        ->where('idpersona', '!=', $personaExistente->id)
                        ->exists();
                    if ($correoEnUso) {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'El correo de esta persona ya está asignado a otro usuario. Cambia el correo en los datos de la persona o contacta a soporte.',
                        ], 409);
                    }
                    $userExistente = new User();
                    $userExistente->id = $personaExistente->id;
                    $userExistente->email = $correoPersona;
                    $userExistente->contrasena = bcrypt($personaExistente->identificacion);
                    $userExistente->idpersona = $personaExistente->id;
                    $userExistente->save();
                }
                DB::commit();

                return response()->json($personaExistente, 200);
            }

            // --- Nueva persona: validar antes de insertar (evita error SQL genérico) ---
            if ($email === '') {
                DB::rollBack();

                return response()->json([
                    'message' => 'El correo electrónico es obligatorio.',
                ], 422);
            }

            $nombre1 = trim((string) $request->input('nombre1', ''));
            $apellido1 = trim((string) $request->input('apellido1', ''));
            if ($nombre1 === '' || $apellido1 === '') {
                DB::rollBack();

                return response()->json([
                    'message' => 'El primer nombre y el primer apellido son obligatorios.',
                ], 422);
            }

            $fechaNacRaw = trim((string) $request->input('fechaNac', ''));
            if ($fechaNacRaw === '' || in_array(strtolower($fechaNacRaw), ['undefined', 'null'], true)) {
                DB::rollBack();

                return response()->json([
                    'message' => 'La fecha de nacimiento es obligatoria.',
                ], 422);
            }

            $idTipoIdentificacion = $this->normalizePositiveInt($request->input('idtipoIdentificacion'));
            $idCiudadNac = $this->normalizePositiveInt($request->input('idciudadNac'));
            $idCiudadUbicacion = $this->normalizePositiveInt($request->input('idciudadUbicacion'));

            if ($idTipoIdentificacion === null) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Selecciona un tipo de identificación válido.',
                ], 422);
            }
            if ($idCiudadNac === null) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Selecciona la ciudad de nacimiento.',
                ], 422);
            }
            if ($idCiudadUbicacion === null) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Selecciona la ciudad de ubicación o residencia.',
                ], 422);
            }

            if (User::where('email', $email)->exists()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'El correo electrónico ya está registrado con otro usuario. Usa un correo distinto o revisa si la persona ya existe con otra cédula.',
                ], 409);
            }

            $maxPersonId = Person::max('id') ?? 0;
            $maxUserId = User::max('id') ?? 0;
            $nextId = max($maxPersonId, $maxUserId) + 1;

            while (Person::where('id', $nextId)->exists() || User::where('id', $nextId)->exists()) {
                $nextId++;
            }

            $persona = new Person();
            $persona->id = $nextId;
            $persona->fechaNac = $fechaNacRaw;
            $persona->idTipoIdentificacion = $idTipoIdentificacion;
            $persona->identificacion = $identificacion;
            $persona->nombre1 = $nombre1;
            $persona->nombre2 = $request->input('nombre2');
            $persona->apellido2 = $request->input('apellido2');
            $persona->apellido1 = $apellido1;
            $persona->idCiudadNac = $idCiudadNac;
            $persona->celular = $request->input('celular');
            $persona->email = $email;
            $persona->direccion = $request->input('direccion');
            $persona->idCiudadUbicacion = $idCiudadUbicacion;
            $ciudadExpedicion = $this->normalizePositiveInt($request->input('ciudadExpedicion') ?? $request->input('idciudadExpedicion'));
            if ($ciudadExpedicion !== null) {
                $persona->ciudadExpedicion = $ciudadExpedicion;
            }
            $persona->telefonoFijo = $request->input('telefonoFijo');
            $persona->sexo = $request->input('sexo');
            $persona->rh = $request->input('rh');
            $persona->perfil = 'N/A';
            $persona->rutaFoto = $this->storeLogoPersona($request);
            $persona->save();

            $user = new User();
            $user->id = $persona->id; 
            $user->email = $email;
            $user->contrasena = bcrypt($identificacion);
            $user->idpersona = $persona->id;
            $user->save();

            $tecero = new Tercero();
            $tecero->nombre = $persona->nombre1 . ' ' . $persona->nombre2 . ' ' . $persona->apellido1 . ' ' . $persona->apellido2;
            $tecero->identificacion = $persona->identificacion;
            $tecero->idTipoTercero = TipoTercero::PERSONA_NATURAL;
            $tecero->idCompany = $company_id;
            $tecero->idTipoIdentificacion = $persona->idTipoIdentificacion;
            $tecero->email = $persona->email;
            $tecero->save();

            DB::commit();
            return response()->json($persona, 201);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Error SQL en storePersona', [
                'message' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null,
            ]);
            $raw = $e->getMessage();
            $isDuplicate = str_contains($raw, 'Duplicate') || (($e->errorInfo[0] ?? '') === '23000');
            if ($isDuplicate) {
                if (stripos($raw, 'email') !== false || stripos($raw, 'usuario') !== false) {
                    return response()->json([
                        'message' => 'El correo electrónico ya está registrado. Usa otro correo o revisa si la persona ya existe.',
                    ], 409);
                }
                if (stripos($raw, 'identificacion') !== false) {
                    return response()->json([
                        'message' => 'Ya existe una persona con esta identificación en el sistema.',
                    ], 409);
                }

                return response()->json([
                    'message' => 'Los datos chocan con un registro existente (duplicado). Revisa correo, cédula y vuelve a intentar.',
                ], 409);
            }
            $payload = [
                'message' => 'Error al guardar en la base de datos. Verifica ciudad, tipo de documento y que no falten datos obligatorios.',
            ];
            if (config('app.debug')) {
                $payload['debug'] = $raw;
            }

            return response()->json($payload, 400);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error en storePersona (creación/actualización de persona)', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            $payload = [
                'message' => 'No se pudo guardar la persona. Verifica los datos e intenta de nuevo. Si el problema continúa, revisa el registro en laravel.log.',
            ];
            if (config('app.debug')) {
                $payload['debug'] = $th->getMessage();
            }

            return response()->json($payload, 400);
        }
    }



    /**
     * Almacena la información de un nuevo contrato junto con sus pagos y asignaciones correspondientes.
     *
     * Esta función crea un nuevo contrato, realiza asignaciones de roles y activa al usuario asociado.
     * También realiza la creación de pagos mensuales, quincenales o indefinidos según el tipo de contrato.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeContrato(Request $request)
    {


        $company_id = KeyUtil::idCompany();

        $persona_id = $request->input('idPersona');
        $fechaInicio = Carbon::parse($request->input('fechaContratacion'));

        try {
            DB::beginTransaction();
            $contrato = new Contract();
            $contrato->idpersona = $persona_id;
            $contrato->idempresa = $company_id;
            $contrato->idCompany = $company_id;
            $contrato->idtipoContrato = $request->input('idtipoContrato');
            $contrato->fechaContratacion = $fechaInicio;

            // Inicializar $fechaFinalContrato para evitar errores más adelante
            $fechaFinalContrato = null;
            
            if ($contrato->idtipoContrato == 6) {
                $contrato->fechaFinalContrato = null;
            } else {
                $fechaFinalContrato = Carbon::parse($request->input('fechaFinalContrato'))->format('Y-m-d');
                $contrato->fechaFinalContrato = $fechaFinalContrato;
            }

            $contrato->valorTotalContrato = $request->input('valorTotalContrato');
            $contrato->salario_id = $request->input('salario_id');
            $contrato->periodoPago = $request->input('periodoPago');
            if ($request->filled('formaPago')) {
                // Normalizamos para que coincida con los valores del enum en BD.
                $formaPago = mb_strtoupper(trim((string) $request->input('formaPago')), 'UTF-8');

                // Tu BD históricamente puede tener la columna con distinto nombre (camelCase vs snake_case).
                // Para evitar "Unknown column ...", asignamos al campo existente.
                if (Schema::hasColumn('contrato', 'formaDePago')) {
                    $contrato->formaDePago = $formaPago;
                } elseif (Schema::hasColumn('contrato', 'formaPago')) {
                    $contrato->formaPago = $formaPago;
                } elseif (Schema::hasColumn('contrato', 'forma_pago')) {
                    $contrato->forma_pago = $formaPago;
                } else {
                    // No asignamos nada para evitar "Unknown column ...".
                }
            }
            if (Schema::hasColumn('contrato', 'supervisorContrato')) {
                $contrato->supervisorContrato = $request->input('supervisorContrato');
            }
            if (Schema::hasColumn('contrato', 'cargoSupervisor')) {
                $contrato->cargoSupervisor = $request->input('cargoSupervisor');
            }
            $contrato->objetoContrato = $request->input('objetoContrato');
            $contrato->observacion = $request->input('observacion');
            $contrato->perfilProfesional = $request->input('perfilProfesional') ?: 'N/A';
            $contrato->otrosi = $request->filled('otrosi')
                ? trim((string) $request->input('otrosi'))
                : 'N';
            $contrato->idEstado = Status::ID_ACTIVE;

            $contrato->idPension = $request->input('idPension');
            $contrato->idArl = $request->input('idArl');
            $contrato->idSalud = $request->input('idSalud');

            $contrato->idCajaCompensacion = $request->input('idCajaCompensacion');
            // idCesantias removido - ya no se usa en el formulario
            if ($request->has('idCesantias') && $request->input('idCesantias')) {
                $contrato->idCesantias = $request->input('idCesantias');
            }
            $contrato->tipoCuentaBancaria = $request->input('tipoCuentaBancaria');
            // tipoCotizante removido - no se envía desde el frontend
            $contrato->numeroCuentaBancaria = $request->input('numeroCuentaBancaria');
            $contrato->idTipoCotizante = $request->input('idTipoCotizante');
            $contrato->idSubTipoCotizante = $request->input('idSubTipoCotizante');
            $contrato->idBanco = $request->input('idBanco');
            $contrato->tipoSalario = $request->input('tipoSalario');
            $contrato->idTarifaRiesgo = $request->input('idTarifaRiesgo');
            $contrato->idActividadRiesgo = $request->input('idActividadRiesgo');
            // idArea puede venir como idCaja desde el frontend
            $contrato->idArea = $request->input('idArea') ?: $request->input('idCaja');
            $contrato->idGrupoNomina = $request->input('idGrupoNomina');
            if ($request->has('idCentroFormacion')) {
                $contrato->idCentroFormacion = $request->input('idCentroFormacion') ?: null;
            }

            // Horas contratadas al mes (puede ser opcional)
            if ($request->has('horasmes')) {
                $contrato->horasmes = $request->input('horasmes');
            }

            if ($request->has('idNivelEducativo')) {
                $contrato->idNivelEducativo = $request->input('idNivelEducativo');
            }

            if (Schema::hasColumn('contrato', 'numeroDocumentoContrato') && $request->has('numeroDocumentoContrato')) {
                $v = $request->input('numeroDocumentoContrato');
                $contrato->numeroDocumentoContrato = $v !== null && $v !== '' ? trim((string) $v) : null;
            }

            // Número de contrato (distinto de número de documento del contrato): si no viene, se usa el id al guardar.
            if (Schema::hasColumn('contrato', 'numeroContrato') && $request->filled('numeroContrato')) {
                $contrato->numeroContrato = trim((string) $request->input('numeroContrato'));
            }

            // Tipo de comisiones - campo removido si no existe en la tabla
            // if ($request->has('tipoComisiones')) {
            //     $contrato->tipoComisiones = $request->input('tipoComisiones');
            // }

            if ($request->has('observacionPreocupacional')) {
                $observacionTexto = trim($request->input('observacionPreocupacional'));

                if (!empty($observacionTexto)) {
                    $observacionPreocupacional = new ObservacionPreocupacional();
                    $observacionPreocupacional->idPersona = $contrato->idpersona;
                    $observacionPreocupacional->observacion = $observacionTexto;
                    $observacionPreocupacional->save();
                }
            }


            $contrato->save();

            if (Schema::hasColumn('contrato', 'numeroContrato')
                && ($contrato->numeroContrato === null || $contrato->numeroContrato === '')) {
                $contrato->numeroContrato = (string) $contrato->id;
                $contrato->save();
            }

            // Guardar áreas de conocimiento
            if ($request->has('areasConocimiento') && is_array($request->input('areasConocimiento'))) {
                // Asegura unicidad y tipo entero antes del sync para evitar violaciones de constraint unique en el pivot.
                $areas = $request->input('areasConocimiento');
                $areas = array_map(function ($value) {
                    return (int)$value;
                }, $areas);
                $areas = array_values(array_unique($areas));
                $contrato->areasConocimiento()->sync($areas);
            }

            // Guardar programas
            if ($request->has('programas') && is_array($request->input('programas'))) {
                $contrato->programas()->sync($request->input('programas'));
            }

            $novedad = new Novedad();
            $novedad->tipo = 'INGRESO';
            $novedad->descripcion = 'Empleado ingresa al sistema';
            $novedad->idContrato = $contrato->id;
            $novedad->estado = 'PENDIENTE';
            $novedad->fechaInicial = now();
            $novedad->save();



            if (in_array($contrato->idtipoContrato, [6, 7])) {
                $vacion = new Vacacion();
                $vacion->idContrato = $contrato->id;
                $vacion->periodo = $fechaInicio->copy()->addYear()->year;
                $vacion->estado = 'PENDIENTE';
                $vacion->save();
            }

            $user = User::where('idpersona', $persona_id)->first();

            if (!$user) {
                throw new \Exception('No se encontró el usuario asociado a la persona (idpersona).', 505);
            }

            // Actualizar el centro de formación del usuario si se proporciona
            if ($request->has('idCentroFormacion') && $request->input('idCentroFormacion')) {
                $user->idCentroFormacion = $request->input('idCentroFormacion');
                $user->save();
            }

            $persona = Person::find($persona_id);

            if (!$persona) {
                throw new \Exception("No se encontró la persona", 505);
            }

            try {
                $correoSend = $this->sendCorreoContrato($persona);
            } catch (\Throwable $mailException) {
                // No bloqueamos la creación del contrato si el envío de correo falla.
                \Log::error('Error enviando correo de contrato', [
                    'message' => $mailException->getMessage(),
                    'persona_id' => $persona_id
                ]);
            }

            $transaccion = $this->storeTransaccionAsignacion($contrato->valorTotalContrato, $contrato->id);


            if ($contrato->idtipoContrato == 8) {
                $salario = new Salario();
                $salario->valor = $request->input('sueldo');
            } else {
                $salario = Salario::find($contrato->salario_id);
                if (!$salario) {
                    throw new \Exception("No se encontró el salario correspondiente al contrato", 505);
                }
            }

            if ($contrato->idtipoContrato == 6) {
                // Si el contrato es del tipo indefinido, crea un pago en el mes actual
                $this->pagoContratoIndefinido($request, $transaccion);
            } else {
                // Si no, procede con los pagos mensuales o quincenales
                if ($contrato->periodoPago == 30) {
                    $this->storePagosPeriodoMensual($contrato, $request, $transaccion);
                } elseif ($contrato->periodoPago == 15) {
                    $this->storePagosPeriodoQuincenal($contrato, $request, $transaccion);
                }
            }


            $activationUser = new ActivationCompanyUser();
            $activationUser->user_id = $user->id;
            $activationUser->state_id = Status::ID_ACTIVE;
            $activationUser->fechaInicio = $fechaInicio;

            if (empty($fechaFinalContrato)) {
                $activationUser->fechaFin = date('Y-m-d', strtotime($fechaInicio . ' + 3 years'));
            } else {
                $activationUser->fechaFin = $fechaFinalContrato;
            }

            $activationUser->saveWithCompany();

            $activationUser->assignRole($request->input('rol'));
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            // IMPORTANTE: no devolver errores crudos de MySQL al frontend (pueden incluir SQL/valores).
            \Log::error('Error al guardar contrato', [
                'exception' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'payload' => $request->except([
                    'rutaFile',
                    'rutaFotoFile',
                    'password',
                    'contrasena',
                    'rutaFoto',
                ]),
            ]);

            $payload = [
                'message' => 'No se pudo crear el contrato. Por favor, intente nuevamente.',
            ];
            if (config('app.debug')) {
                $payload['debug'] = $th->getMessage();
            }

            return response()->json($payload, 500);
        }

        return response()->json($contrato, 201);
    }



    /**
     * Envía un correo electrónico de bienvenida al finalizar el proceso de contratación.
     *
     * Esta función toma la información de la persona, como su correo electrónico,
     * y envía un correo de bienvenida con los detalles necesarios para iniciar sesión.
     *
     * @param \App\Models\Persona
     * @return void
     */
    private function sendCorreoContrato($persona)
    {
        $correoPersona = $persona->email;
        $url = 'https://admin.virtualt.org/#/login';
        $subject = "¡Bienvenido a Virtual Technology!";
        $message = "¡Felicitaciones! Tu proceso de contratación ha finalizado con éxito.

        Para acceder a tu cuenta, sigue estos pasos:
        1. Ingresa a la plataforma en $url
        2. Utiliza la siguiente información de inicio de sesión:
           - Correo electrónico: $correoPersona
           - Contraseña: {$persona->identificacion}";

        $mailService = new MailService($subject, $message);
        Mail::to($correoPersona)->send($mailService);
    }



    /**
     * Almacena una transacción y la asigna a un contrato.
     *
     * Esta función crea una nueva transacción con la información proporcionada,
     * la marca como pendiente y la asigna al contrato especificado. Devuelve la
     * instancia de la transacción creada.
     *
     * @param float $valor - El valor de la transacción.
     * @param int $contratoId - El ID del contrato al que se asignará la transacción.
     * @return \App\Models\Transaccion - La instancia de la transacción creada.
     */
    private function storeTransaccionAsignacion($valor, $contratoId)
    {
        $transaccion = new Transaccion();
        $transaccion->fechaTransaccion = Carbon::now()->toDateString();
        $transaccion->hora = Carbon::now()->format('H:i');
        $transaccion->valor = $valor;
        $transaccion->idTipoTransaccion = TipoTransaccion::NOMINA;
        $transaccion->idTipoPago = TipoPago::CONTADO;
        $transaccion->idEstado = Status::ID_ACTIVE;
        $transaccion->save();


        $asignacionContratoTransaccion = new ContratoTransaccion();
        $asignacionContratoTransaccion->contrato_id = $contratoId;
        $asignacionContratoTransaccion->transaccion_id = $transaccion->id;
        $asignacionContratoTransaccion->save();

        return $transaccion;
    }



    /**
     * Almacena los pagos mensuales para un contrato durante todo el periodo del contrato.
     *
     * Esta función calcula el número de meses entre la fecha de inicio y finalización
     * del contrato, y crea un pago mensual para cada mes en ese periodo. Los pagos se
     * asocian a la transacción proporcionada.
     *
     * @param \App\Models\Contrato
     * @param \Illuminate\Http\Request
     * @param \App\Models\Transaccion
     * @return void
     */
    private function storePagosPeriodoMensual($contrato, $request, $transaccion)
    {
        $fechaInicio = Carbon::parse($contrato->fechaContratacion);
        $fechaFin = Carbon::parse($contrato->fechaFinalContrato);
        $mesesContrato = $fechaInicio->diffInMonths($fechaFin);


        for ($i = 0; $i  <= $mesesContrato; $i++) {
            $pago = new Pago();
            $pago->idMedioPago = 4;
            $pago->valor = $request->input('sueldo');
            $pago->idTransaccion = $transaccion->id;
            $pago->idEstado = Status::ID_PENDIENTE;

            $fechaPago = $fechaInicio->copy()->addMonths($i)->day(30);

            if ($fechaPago->month == 2) {
                $pago->fechaPago = $fechaPago->day(28)->format('Y-m-d');
            } elseif ($fechaPago->day == 1 && $fechaPago->month == 3) {
                $pago->fechaPago = $fechaPago->day(28)->subMonth()->format('Y-m-d');
            } else {
                $pago->fechaPago = $fechaPago->format('Y-m-d');
            }

            if (Carbon::parse($pago->fechaPago)->lt(Carbon::now())) {
                $pago->idEstado = 5;
                $pago->fechaReg = $pago->fechaPago;
                $pago->observacion = "Registro migratorio";
            }

            $pago->save();
        }
    }



    /**
     * Almacena los pagos quincenales para un contrato durante todo el periodo del contrato.
     *
     * Esta función calcula el número de meses entre la fecha de inicio y finalización
     * del contrato, y crea dos pagos quincenales para cada mes en ese periodo. Los pagos
     * se asocian a la transacción proporcionada.
     *
     * @param \App\Models\Contrato
     * @param \Illuminate\Http\Request
     * @param \App\Models\Transaccion
     * @return void
     */
    private function storePagosPeriodoQuincenal($contrato, $request, $transaccion)
    {
        $fechaInicio = Carbon::parse($contrato->fechaContratacion);
        $fechaFin = Carbon::parse($contrato->fechaFinalContrato);
        $mesesContrato = $fechaInicio->diffInMonths($fechaFin);

        for ($i = 0; $i  <= $mesesContrato; $i++) {
            $pago = new Pago();
            $pago->idMedioPago = 4;
            $pago->valor = $request->input('sueldo');
            $pago->idTransaccion = $transaccion->id;
            $pago->idEstado = Status::ID_PENDIENTE;

            // Pago 1 - Día 15
            $fechaPago1 = $fechaInicio->copy()->addMonths($i)->day(15);
            if ($fechaPago1->month == 2) {
                $pago->fechaPago = $fechaPago1->day(28)->format('Y-m-d');
            } else {
                $pago->fechaPago = $fechaPago1->format('Y-m-d');
            }

            if (Carbon::parse($pago->fechaPago)->lt(Carbon::now())) {
                $pago->idEstado = 5;
                $pago->idMedioPago = 4;
                $pago->fechaReg = $pago->fechaPago;
                $pago->observacion = "Registro migratorio";
            }
            $pago->save();

            // Pago 2 - Día 30 o 28 en febrero
            $fechaPago2 = $fechaInicio->copy()->addMonths($i)->day(30);
            if ($fechaPago2->month == 2) {
                $pago = new Pago();
                $pago->idMedioPago = 4;
                $pago->valor = $request->input('sueldo');
                $pago->idTransaccion = $transaccion->id;
                $pago->idEstado = Status::ID_PENDIENTE;
                $pago->fechaPago = $fechaPago2->day(28)->format('Y-m-d');
            } else {
                $pago = new Pago();
                $pago->valor = $request->input('sueldo');
                $pago->idMedioPago = 4;
                $pago->idTransaccion = $transaccion->id;
                $pago->idEstado = Status::ID_PENDIENTE;
                $pago->fechaPago = $fechaPago2->format('Y-m-d');
            }

            if (Carbon::parse($pago->fechaPago)->lt(Carbon::now())) {
                $pago->idEstado = 5;
                $pago->idMedioPago = 4;
                $pago->fechaReg = $pago->fechaPago;
                $pago->observacion = "Registro migratorio";
            }

            $pago->save();
        }
    }



    /**
     * Realiza el pago mensual para un contrato indefinido.
     *
     * Esta función crea un pago mensual para un contrato indefinido y lo asocia a la
     * transacción proporcionada. La fecha de pago se establece según la fecha actual,
     * utilizando el último día del mes o el día 28 en caso de febrero.
     *
     * @param \Illuminate\Http\Request
     * @param \App\Models\Transaccion
     * @return void
     */
    private function pagoContratoIndefinido($request, $transaccion)
    {
        $pago = new Pago();
        $pago->idMedioPago = 4;
        $pago->valor = $request->input('sueldo');
        $pago->idTransaccion = $transaccion->id;
        $pago->idEstado = Status::ID_PENDIENTE;


        $fechaActual = Carbon::now();

        if ($fechaActual->month == 2) {

            $pago->fechaPago = $fechaActual->day(28)->format('Y-m-d');
        } else {

            $pago->fechaPago = $fechaActual->day(30)->format('Y-m-d');
        }

        $pago->save();
    }



    /**
     * Almacena o actualiza los documentos asociados a un contrato.
     *
     * Esta función almacena nuevos documentos o actualiza uno existente asociado a un contrato.
     * La información del documento se toma de la solicitud y se guarda en la base de datos.
     * Retorna un objeto JSON con la información del documento almacenado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeDocumentoContrato(Request $request)
    {

        $id = $request->input('id', 0);

        $documentoContrato = DocumentoContrato::find($id);
        if (!$documentoContrato) {
            $documentoContrato = new DocumentoContrato();
        }

        $documentoContrato->ruta = $this->storeRutaDocumento($request);
        $documentoContrato->idContrato = $request->input('idContrato');
        $documentoContrato->idAsignacionTipoDocumentoProceso = $request->input('idAsignacionTipoDocumentoProceso');
        $documentoContrato->fechaCarga = \Carbon\Carbon::now()->toDateTimeString();

        $documentoContrato->save();

        return response()->json($documentoContrato, 201);
    }



    /**
     * Almacena la ruta del documento asociado a un contrato.
     *
     * Esta función determina la ruta del documento basándose en la solicitud.
     * Si se proporciona un archivo en la solicitud ('rutaFile'), utiliza esa información
     * para almacenar el documento en el sistema de archivos. De lo contrario, utiliza una
     * ruta predeterminada especificada en la constante RUTA_DOCUMENTO_DEFAULT.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  bool  $default  Indica si se debe utilizar la ruta predeterminada cuando no se proporciona un archivo.
     * @return string|null  La ruta del documento o null si no se proporciona un archivo y no se utiliza la ruta predeterminada.
     */
    private function storeRutaDocumento(Request $request, $default = true)
    {
        $rutaDocumento = null;

        if ($default) {
            $rutaDocumento = DocumentoContrato::RUTA_DOCUMENTO_DEFAULT;
        }

        if ($request->hasFile('rutaFile')) {
            $rutaDocumento =
                '/storage/' .
                $request
                ->file('rutaFile')
                ->store(DocumentoContrato::RUTA_DOCUMENTO, ['disk' => 'public']);
        }

        return $rutaDocumento;
    }



    /**
     * Convierte un valor de request en entero > 0 o null (rechaza "", "undefined", "null", no numérico).
     */
    private function normalizePositiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $v = trim($value);
            if ($v === '' || strtolower($v) === 'undefined' || strtolower($v) === 'null') {
                return null;
            }
            if (! ctype_digit($v)) {
                return null;
            }
            $n = (int) $v;

            return $n > 0 ? $n : null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (int) $value;

        return $n > 0 ? $n : null;
    }

    /**
     * Almacena la ruta de la imagen asociada a una persona.
     *
     * Esta función determina la ruta de la basándose en la solicitud.
     * Si se proporciona un archivo en la solicitud ('rutaFotoFile'), utiliza esa información
     * para almacenar el logo en el sistema de archivos. De lo contrario, utiliza una
     * ruta predeterminada especificada en la constante RUTA_FOTO_DEFAULT.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  bool  $default  Indica si se debe utilizar la ruta predeterminada cuando no se proporciona un archivo.
     * @return string|null  La ruta del logo o null si no se proporciona un archivo y no se utiliza la ruta predeterminada.
     */
    private function storeLogoPersona(Request $request, $default = true)
    {
        $rutaFoto = null;

        if ($default) {
            $rutaFoto = Person::RUTA_FOTO_DEFAULT;
        }

        if ($request->hasFile('rutaFotoFile')) {
            $rutaFoto =
                '/storage/' .
                $request
                ->file('rutaFotoFile')
                ->store(Person::RUTA_FOTO, ['disk' => 'public']);
        }

        return $rutaFoto;
    }



    /**
     * Obtiene todos los contratos, incluyendo información de persona, salario, transacciones y estado.
     *
     * Esta función busca todos los contratos con relaciones cargadas para 'persona', 'salario.rol',
     * 'transacciones.pago' y 'estado'. Ordena por prioridad de estado y luego por código (id) ascendente.
     * Retorna un objeto JSON con la información de los contratos.
     *
     * @return \Illuminate\Http\JsonResponse
     */
public function getAllContratos(Request $request)
{
    try {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        
        // Obtener los roles del usuario
        $userRoles = [];
        $activationUser = ActivationCompanyUser::where('user_id', $user->id)->first();
        
        if ($activationUser) {
            $userRoles = $activationUser->roles->pluck('name')->toArray();
        }
        
        if (config('app.debug')) {
            Log::debug('getAllContratos', [
                'user_id' => $user->id,
                'roles' => $userRoles,
                'params' => $request->all(),
            ]);
        }

        // Iniciar la consulta base
        $query = Contract::with(
            'persona',
            'salario.rol',
            'transacciones.pago',
            'estado',
            'area'
        )
        ->where('idEstado', '!=', 14)
        ->orderByRaw('CASE WHEN idEstado = 13 THEN 2 WHEN idEstado = 2 THEN 1 ELSE 0 END')
        ->orderBy('id');

        // Validar roles específicos o dar contratos por centro
        if (in_array('ADMINISTRADOR VT', $userRoles)) {
            if ($request->has('idCompany') && $request->input('idCompany')) {
                $idCompany = $request->input('idCompany');
                $query->where(function ($q) use ($idCompany) {
                    $q->where('idCompany', $idCompany)
                      ->orWhere('idempresa', $idCompany);
                });
            }

            if ($request->has('idCentroFormacion') && $request->input('idCentroFormacion')) {
                $idCentroFormacion = $request->input('idCentroFormacion');
                $query->where('idCentroFormacion', $idCentroFormacion);
            }
        } elseif (in_array('ADMIN REGIONAL', $userRoles)) {
            if ($request->has('idCentroFormacion') && $request->input('idCentroFormacion')) {
                $idCentroFormacion = $request->input('idCentroFormacion');
                $query->where('idCentroFormacion', $idCentroFormacion);
            }
        } else {
            if ($user->idCentroFormacion) {
                $query->where('idCentroFormacion', $user->idCentroFormacion);
            } else {
                return response()->json(['error' => 'No tienes centro asignado'], 403);
            }
        }

        $contratos = $query->get();

        if (config('app.debug')) {
            Log::debug('getAllContratos resultado', ['count' => $contratos->count()]);
        }

        return response()->json($contratos);
        
    } catch (\Exception $e) {
        Log::error('Error en getAllContratos', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $payload = [
            'error' => 'Error al obtener los contratos',
            'message' => 'No se pudieron cargar los contratos. Intente más tarde.',
        ];
        if (config('app.debug')) {
            $payload['debug'] = $e->getMessage();
        }

        return response()->json($payload, 500);
    }
}
 
/**
 * Endpoint específico para ADMINISTRADOR VT - Flujo completo
 * Empresa → Centros → Contratos filtrados
 */
public function getContratosFlujoVT(Request $request)
{
    try {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        if (config('app.debug')) {
            Log::debug('getContratosFlujoVT', [
                'user_id' => $user->id,
                'params' => $request->all(),
            ]);
        }

        $userRoles = [];
        $activationUser = ActivationCompanyUser::where('user_id', $user->id)->first();

        if ($activationUser) {
            $userRoles = $activationUser->roles->pluck('name')->toArray();
        }

        if (!in_array('ADMINISTRADOR VT', $userRoles)) {
            return response()->json(['error' => 'Este endpoint es solo para ADMINISTRADOR VT'], 403);
        }

        $request->validate([
            'idCompany' => 'required|integer|exists:empresa,id',
            'idCentroFormacion' => 'nullable|integer|exists:centroFormacion,id',
        ]);

        $idCompany = $request->input('idCompany');
        $idCentroFormacion = $request->input('idCentroFormacion');

        $empresa = Company::find($idCompany);
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        $centrosQuery = CentrosFormacion::where('idEmpresa', $idCompany)
            ->with(['ciudad:id,descripcion', 'empresa:id,razonSocial'])
            ->orderBy('nombre', 'asc');

        if ($idCentroFormacion) {
            $centrosQuery->where('id', $idCentroFormacion);
        }

        $centros = $centrosQuery->get();

        $contratosQuery = Contract::with(
            'persona',
            'salario.rol',
            'transacciones.pago',
            'estado',
            'area'
        )
        ->where('idEstado', '!=', 14)
        ->where(function ($q) use ($idCompany) {
            $q->where('idCompany', $idCompany)
              ->orWhere('idempresa', $idCompany);
        })
        ->orderByRaw('CASE WHEN idEstado = 13 THEN 2 WHEN idEstado = 2 THEN 1 ELSE 0 END')
        ->orderBy('id');

        if ($idCentroFormacion) {
            $contratosQuery->where('idCentroFormacion', $idCentroFormacion);
        }

        $contratos = $contratosQuery->get();

        // 4. Preparar respuesta completa
        $response = [
            'status' => 'success',
            'message' => 'Datos obtenidos correctamente',
            'data' => [
                'empresa' => [
                    'id' => $empresa->id,
                    'razonSocial' => $empresa->razonSocial
                ],
                'centros' => $centros->map(function($centro) {
                    return [
                        'id' => $centro->id,
                        'nombre' => $centro->nombre,
                        'direccion' => $centro->direccion,
                        'telefono' => $centro->telefono,
                        'ciudad' => $centro->ciudad?->descripcion,
                        'empresa' => $centro->empresa?->razonSocial
                    ];
                }),
                'contratos' => $contratos->map(function ($contrato) {
                    $p = $contrato->persona;

                    return [
                        'id' => $contrato->id,
                        'persona' => [
                            'nombreCompleto' => $p
                                ? trim(
                                    ($p->nombre1 ?? '') . ' ' . ($p->nombre2 ?? '') . ' ' .
                                    ($p->apellido1 ?? '') . ' ' . ($p->apellido2 ?? '')
                                )
                                : '',
                            'identificacion' => $p->identificacion ?? null,
                            'nombre1' => $p->nombre1 ?? '',
                            'nombre2' => $p->nombre2 ?? '',
                            'apellido1' => $p->apellido1 ?? '',
                            'apellido2' => $p->apellido2 ?? '',
                            'rutaFotoUrl' => $p->rutaFotoUrl ?? null,
                        ],
                        'salario' => [
                            'rol' => $contrato->salario?->rol?->name
                        ],
                        'area' => [
                            'nombre' => $contrato->area?->nombre
                        ],
                        'estado' => [
                            'estado' => $contrato->estado?->estado
                        ],
                        'fechaContratacion' => $contrato->fechaContratacion,
                        'fechaFinalContrato' => $contrato->fechaFinalContrato,
                        'idCompany' => $contrato->idCompany,
                        'idempresa' => $contrato->idempresa,
                        'idCentroFormacion' => $contrato->idCentroFormacion
                    ];
                })
            ],
            'filters' => [
                'idCompany' => $idCompany,
                'idCentroFormacion' => $idCentroFormacion,
                'hasCentroFilter' => $idCentroFormacion ? true : false
            ],
            'counts' => [
                'centros' => $centros->count(),
                'contratos' => $contratos->count()
            ]
        ];

        return response()->json($response);

    } catch (\Exception $e) {
        Log::error('Error en getContratosFlujoVT', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $payload = [
            'error' => 'Error al obtener los datos',
            'message' => 'No se pudieron cargar los contratos. Intente más tarde.',
        ];
        if (config('app.debug')) {
            $payload['debug'] = $e->getMessage();
        }

        return response()->json($payload, 500);
    }
}
    
    /**
     * Obtiene un contrato por su ID, incluyendo información detallada y otros contratos relacionados
     * en donde el idContrato sea igual a $id.
     *
     * Esta función busca un contrato por su ID con relaciones cargadas para información detallada,
     * como documentos del contrato, persona, salario, tipo de contrato, empresa, estado, archivo del contrato
     * y transacciones de pago. También obtiene otros contratos relacionados con el mismo idContrato.
     * Retorna un objeto JSON con la información del contrato y otros contratos relacionados.
     *
     * @param  int  $id  El ID del contrato a obtener.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContratoById($id)
    {
        $contract = Contract::with([
            'documentosContrato.AsignacionTipoDocumentoProceso.tipoDocumento',
            'persona.ciudadUbicacion',
            'persona.ciudadNac',
            'persona.ciudadExpedicion.departamento',
            'persona.tipoIdentificacion',
            'persona.observacionesPreocupacionales',
            'persona.usuario.centroFormacion',
            'persona.usuario.centroFormacion.ciudad',
            'persona.usuario.centroFormacion.empresa',
            'salario.rol',
            'tipoContrato',
            'empresa',
            'banco',
            'estado',
            'archivoContrato',
            'pension',
            'arl',
            'salud',
            'cajaCompensacion',
            'cesantias',
            'area',
            'actividadRiesgo',
            'tipoCotizante',
            'tarifasRiesgo',
            'nivelEducativo',
            'areasConocimiento',
            'programas',
            'transacciones.pago' => function ($query) {
                $query->first();
            }
        ])->find($id);

        if (!$contract) {
            return response()->json(['error' => 'Contrato no encontrado'], 404);
        }


        if ($contract->documentosContrato->isEmpty()) {
            // Buscar la relación 'documentosContrato' con el 'idContrato' del contrato encontrado
            $documentosContrato = DocumentoContrato::where('idContrato', $contract->idContrato)
                ->with('AsignacionTipoDocumentoProceso.tipoDocumento')
                ->get();


            $contract->documentos_contrato = $documentosContrato;
        }

        $otrosContratos = Contract::with('archivoContrato')
            ->whereHas('archivoContrato', function ($query) use ($contract) {
                $query->where('idContrato', $contract->idContrato);
            })
            ->where('id', '!=', $id)
            ->get();

        $contract->otrosContratos = $otrosContratos;

        return response()->json($contract);
    }



    public function updateDocumentoContrato(Request $request)
    {
        $idDocumento = $request->input('idDocumento');


        $documentoContrato = DocumentoContrato::find($idDocumento);

        if (!$documentoContrato) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }


        $rutaDocumento = $this->storeRutaDocumento($request);
        if ($rutaDocumento) {
            $documentoContrato->ruta = $rutaDocumento;
        } else {
            return response()->json(['error' => 'Error al almacenar el documento'], 500);
        }
        $documentoContrato->save();

        return response()->json($documentoContrato);
    }

    
    public function deleteDocumentoContrato(Request $request)
    {
        $idDocumento = $request->input('idDocumento');

        $documentoContrato = DocumentoContrato::find($idDocumento);

        if (!$documentoContrato) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $documentoContrato->delete();

        return response()->json(['message' => 'Documento eliminado correctamente']);
    }

    /**
     * Descarga o visualiza un documento de contrato
     *
     * @param int $idDocumento
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadDocumentoContrato($idDocumento, Request $request)
    {
        // Si el token viene como query parameter, establecerlo en el header para autenticación
        if ($request->has('token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        $documentoContrato = DocumentoContrato::with('AsignacionTipoDocumentoProceso.tipoDocumento')->find($idDocumento);

        if (!$documentoContrato) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $rutaArchivo = $documentoContrato->ruta;

        // Si no hay ruta o es la ruta por defecto, retornar error
        if (!$rutaArchivo || $rutaArchivo === DocumentoContrato::RUTA_DOCUMENTO_DEFAULT) {
            return response()->json(['error' => 'Archivo no disponible'], 404);
        }

        // Eliminar el prefijo /storage/ si existe
        $rutaArchivoRelativa = parse_url($rutaArchivo, PHP_URL_PATH);
        $rutaArchivoSinStorage = str_replace('/storage/', 'app/public/', $rutaArchivoRelativa);
        $rutaCompleta = storage_path($rutaArchivoSinStorage);

        // Verificar que el archivo existe
        if (!file_exists($rutaCompleta)) {
            return response()->json(['error' => 'Archivo no encontrado en el servidor'], 404);
        }

        // Obtener el tipo MIME del archivo
        $mimeType = mime_content_type($rutaCompleta);
        if (!$mimeType) {
            $mimeType = 'application/octet-stream';
        }

        // Obtener el nombre del archivo original si está disponible
        $nombreArchivo = $documentoContrato->AsignacionTipoDocumentoProceso?->tipoDocumento?->tituloDocumento ?? 'documento';
        $extension = pathinfo($rutaCompleta, PATHINFO_EXTENSION);
        $nombreArchivoCompleto = $nombreArchivo . '.' . $extension;

        // Retornar el archivo para visualización/descarga
        return response()->file($rutaCompleta, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $nombreArchivoCompleto . '"'
        ]);
    }


    /**
     * Obtiene un solo contrato por su ID, incluyendo información detallada.
     *
     * Esta función busca un contrato por su ID con relaciones cargadas para información detallada,
     * como salario, tipo de contrato, estado, archivo del contrato y transacciones de pago.
     * Retorna un objeto JSON con la información del contrato.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOneContratoById(Request $request)
    {
        $idContrato = $request->input('idContrato');

        $contrato = Contract::with(
            'salario.rol',
            'tipoContrato',
            'estado',
            'archivoContrato',
            'transacciones.pago'
        )->find($idContrato);

        if (!$contrato) {
            return response()->json(['error' => 'Contrato no encontrado'], 404);
        }

        return response()->json([$contrato]);
    }



    /**
     * Interrumpe un contrato y realiza las acciones asociadas.
     *
     * Esta función interrumpe un contrato, guarda un registro de archivo relacionado,
     * y realiza actualizaciones en el estado del contrato, las transacciones y los pagos.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function interrumpirContrato(Request $request)
    {
        $idContrato = $request->input('idContrato');
        $idTipoTerminacionContrato = $request->input('idTipoTerminacionContrato');

        $archivoContrato = new ArchivoContrato();
        $archivoContrato->idContrato = $idContrato;
        $archivoContrato->observacion = $request->input('observacion');
        $archivoContrato->fecha = now();
        $archivoContrato->url = $this->storeArchivoContrato($request);
        $archivoContrato->save();

        $contrato = Contract::with(['transacciones', 'transacciones.pago', 'persona'])->find($idContrato);

        if ($contrato) {
            $contrato->idTipoTerminoContrato = $idTipoTerminacionContrato;
            $contrato->save();

            $personaDelContrato = $contrato->persona;
            $activationCompanyUser = ActivationCompanyUser::where('user_id', $personaDelContrato->id)->first();

            $this->sendCorreoInterrpcionContrato($personaDelContrato, $archivoContrato);
            $this->storeNotificacionInterrupcionContrato($personaDelContrato, $archivoContrato);

            // Actualiza el estado de la activación de usuario a inactivo
            if ($activationCompanyUser) {
                $activationCompanyUser->state_id = Status::ID_INACTIVO;
                $activationCompanyUser->fechaFin = now();
                $activationCompanyUser->save();
            }


            $novedad = new Novedad();
            $novedad->tipo = 'RETIRO';
            $novedad->descripcion = 'Retiro empleado del sistema';
            $novedad->idContrato = $contrato->id;
            $novedad->estado = 'PENDIENTE';
            $novedad->fechaInicial = now();
            $novedad->save();

            // $this->updatePagosContratoInterrumpido($contrato);
        }

        return response()->json(['message' => 'Contrato interrumpido con éxito'], 200);
    }



    /**
     * Actualiza los pagos y transacciones asociadas a un contrato interrumpido.
     *
     * Esta función realiza diversas acciones para gestionar el estado de los pagos y transacciones
     * asociadas a un contrato que ha sido interrumpido. Esto incluye la actualización de estados,
     * el cálculo de valores y la creación de nuevas transacciones y pagos.
     *
     * @param \App\Models\Contract $contrato El contrato que ha sido interrumpido.
     * @return void
     */
    private function updatePagosContratoInterrumpido($contrato)
    {
        if ($contrato) {
            $contrato->idEstado = Status::ID_INTERRUMPIDO;
            $contrato->save();

            $transacciones = $contrato->transacciones;

            foreach ($transacciones as $transaccion) {
                $transaccion->update(['idEstado' => Status::ID_INTERRUMPIDO]);

                $pagosRelacionados = $transaccion->pago;

                foreach ($pagosRelacionados as $pagoRelacionado) {
                    if ($pagoRelacionado->idEstado == Status::ID_PENDIENTE) {
                        $pagoRelacionado->update(['idEstado' => Status::ID_INTERRUMPIDO]);
                    }
                }
            }

            $primerPagoRelacionado = $transacciones->first()->pago->first();

            $today = Carbon::now();
            $daysInMonth = $today->daysInMonth;
            $diasTranscurridos = Carbon::now()->day;
            $valorPorDia = round($primerPagoRelacionado->valor / $daysInMonth);
            $valorTotal = $diasTranscurridos * $valorPorDia;

            $transaccion = new Transaccion();
            $transaccion->fechaTransaccion = Carbon::now()->toDateString();
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->valor = $valorTotal;
            $transaccion->idTipoTransaccion = TipoTransaccion::LIQUIDACION;
            $transaccion->idTipoPago = TipoPago::CONTADO;
            $transaccion->idEstado = Status::ID_PENDIENTE;
            $transaccion->save();


            $asignacionContratoTransaccion = new ContratoTransaccion();
            $asignacionContratoTransaccion->contrato_id = $contrato->id;
            $asignacionContratoTransaccion->transaccion_id = $transaccion->id;
            $asignacionContratoTransaccion->save();


            $pago = new Pago();
            $pago->idMedioPago = 4;

            $fechaPago = Carbon::now();
            $fechaPago->day(30);

            if ($fechaPago->greaterThan(Carbon::now())) {
                $fechaPago = Carbon::now()->day(30);
            }

            if ($fechaPago->month == 2 && $fechaPago->day > 28) {
                $fechaPago->day(28);
            }

            $pago->fechaPago = $fechaPago->toDateString();
            $pago->valor = $valorTotal;
            $pago->idTransaccion = $transaccion->id;
            $pago->idEstado = Status::ID_PENDIENTE;
            $pago->save();
        }
    }


    /**
     * Envía un correo electrónico informando sobre la interrupción de un contrato.
     *
     * Esta función envía un correo electrónico a la persona asociada a un contrato,
     * notificándole sobre la interrupción del contrato y adjuntando detalles adicionales.
     *
     * @param \App\Models\Person
     * @param \App\Models\ArchivoContrato
     * @return void
     */
    private function sendCorreoInterrpcionContrato($personaDelContrato, $archivoContrato)
    {
        $subject = "Interrupción de Contrato";

        $message = "Estimado(a) {$personaDelContrato->nombre1},\n\n";
        $message .= "Número de identificación: {$personaDelContrato->identificacion}\n";
        $message .= "Queremos expresar nuestro agradecimiento por tu compromiso y tu tiempo en nuestra empresa. ";
        $message .= "Lamentablemente, te informamos que se ha realizado la interrupción del contrato asociado a tu cuenta. ";
        $message .= "A continuación, proporcionamos detalles adicionales:\n\n";
        $message .= "Observación: {$archivoContrato->observacion}\n";
        $message .= "Fecha de interrupción: {$archivoContrato->fecha->format('Y-m-d H:i:s')}\n\n";
        $message .= "Apreciamos tu contribución y estamos disponibles para cualquier consulta que puedas tener. ";
        $message .= "Te agradecemos por tu dedicación durante tu estadía en nuestra empresa.\n\n";
        $message .= "Atentamente,\n";
        $message .= "El equipo de Virtual Technology.\n\n";


        $archivoContrato = ArchivoContrato::findOrFail($archivoContrato->id);
        $url = $archivoContrato->url;

        $url = str_replace('/storage/archivosContrato/', '', $url);
        $mailService = new MailService($subject, $message);
        $mailService->attach(storage_path("app/public/archivosContrato/{$url}"), [
            'as' => 'InterrupcionContrato.pdf',
        ]);

        Mail::to($personaDelContrato->email)->send($mailService);
    }



    /**
     * Almacena una notificación sobre la interrupción de un contrato.
     *
     * Esta función crea y almacena una notificación informando sobre la interrupción de un contrato
     * dirigida al gerente de Virtual Technology, incluyendo detalles relevantes del contrato interrumpido.
     *
     * @param \App\Models\Person
     * @param \App\Models\ArchivoContrato
     * @return void
     */
    private function storeNotificacionInterrupcionContrato($personaDelContrato, $archivoContrato)
    {
        $subject = "Interrupción de Contrato";

        $message2 = "Estimado(a) Gerente de Virtual Technology ,\n\n";
        $message2 .= "Queremos informarte que has realizado la interrupción del contrato asociado a la cuenta de {$personaDelContrato->nombre1}. ";
        $message2 .= "Número de identificación: {$personaDelContrato->identificacion}\n";
        $message2 .= "A continuación, proporcionamos detalles adicionales:\n\n";
        $message2 .= "Observación: {$archivoContrato->observacion}\n";
        $message2 .= "Fecha de interrupción: {$archivoContrato->fecha->format('Y-m-d H:i:s')}\n\n";
        $message2 .= "Atentamente,\n";
        $message2 .= "El equipo de Virtual Technology.\n\n";

        $notification = new Notificacion();
        $notification->estado_id = Status::ID_ACTIVE;
        $notification->asunto = $subject;
        $notification->mensaje =  $message2;
        $notification->route =  '';
        $notification->idUsuarioReceptor = auth()->user()->id;
        $notification->idUsuarioRemitente =  auth()->user()->id;
        $notification->idEmpresa = KeyUtil::idCompany();
        $notification->idTipoNotificacion = 1;
        $notification->fecha = Carbon::now()->toDateTimeString();
        $notification->hora = Carbon::now()->format('H:i:s');
        $notification->save();
    }



    /**
     * Almacena la ruta del archivo del contrato (archivo de extensión o interrupción),
     *
     * Esta función toma una solicitud y, opcionalmente, un valor predeterminado para la ruta del archivo del contrato.
     * Si se proporciona un archivo en la solicitud, se almacena y se devuelve la nueva ruta del archivo. Si no se proporciona
     * un archivo y se especifica un valor predeterminado, se devuelve la ruta predeterminada. Si no se proporciona un archivo
     * ni se especifica un valor predeterminado, se devuelve `null`.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  bool  $default
     * @return string|null
     */
    private function storeArchivoContrato(Request $request, $default = true)
    {

        $rutaArchivoContrato = null;

        if ($default) {
            $rutaArchivoContrato = ArchivoContrato::RUTA_ARCHIVOS_CONTRATO_DEFAULT;
        }

        if ($request->hasFile('rutaArchivoContratoFile')) {
            $rutaArchivoContrato =
                '/storage/' .
                $request
                ->file('rutaArchivoContratoFile')
                ->store(ArchivoContrato::RUTA_ARCHIVOS_CONTRATO, ['disk' => 'public']);
        }

        return $rutaArchivoContrato;
    }



    /**
     * Extiende un contrato existente en la base de datos.
     *
     * Esta función maneja la extensión de un contrato existente, creando un nuevo contrato
     * con la información proporcionada y actualizando el estado del contrato anterior y sus
     * transacciones. También realiza operaciones relacionadas, como el envío de correos y la
     * actualización de la fecha de acceso a la aplicación para el usuario asociado.
     *
     * @param \Illuminate\Http\Request $request - La instancia de la solicitud HTTP.
     * @return \Illuminate\Http\JsonResponse - Una respuesta JSON que indica el éxito o fallo de la operación.
     */
    public function extenderContrato(Request $request)
    {

        $idContrato = $request->input('idContrato');
        $company_id = KeyUtil::idCompany();
        $persona_id = $request->input('idpersona');
        $fechaInicio = \Carbon\Carbon::parse($request->input('fechaContratacion'));

        try {
            DB::beginTransaction();
            $contrato = new Contract();
            $contrato->idpersona = $persona_id;
            $contrato->idempresa = $company_id;
            $contrato->idtipoContrato = $request->input('idtipoContrato');
            $contrato->fechaContratacion = $fechaInicio;
            $fechaFinalContrato = Carbon::parse($request->input('fechaFinalContrato'))->format('Y-m-d');
            $contrato->fechaFinalContrato = $fechaFinalContrato;
            $contrato->valorTotalContrato = $request->input('valorTotalContrato');
            $contrato->salario_id = $request->input('salario_id');
            $contrato->periodoPago = $request->input('periodoPago');
            $contrato->objetoContrato = $request->input('objetoContrato');
            $contrato->observacion = $request->input('observacion');
            $contrato->perfilProfesional = $request->input('perfilProfesional') ?: 'N/A';
            $contrato->otrosi = 'S';
            $contrato->idContrato = $idContrato;
            $contrato->idEstado = Status::ID_ACTIVE;
            $contrato->save();

            if (Schema::hasColumn('contrato', 'numeroContrato')
                && ($contrato->numeroContrato === null || $contrato->numeroContrato === '')) {
                $contrato->numeroContrato = (string) $contrato->id;
                $contrato->save();
            }

            $user = User::where('idpersona', $persona_id)->first();

            if (!$user) {
                throw new \Exception('No se encontró el usuario asociado a la persona (idpersona).', 505);
            }

            $persona = Person::find($persona_id);

            if (!$persona) {
                throw new \Exception("No se encontró la persona", 505);
            }

            $sendCorreoExtensionContrato = $this->sendCorreoExtensionContrato($persona);
            $transaccion = $this->storeTransaccionAsignacion($contrato->valorTotalContrato, $contrato->id);
            $sendNotificacionExtensionContrato = $this->storeNotficationExtensionContrato($persona);
            $storeTrazabilidadContrato = $this->storeTrazabilidadContrato($idContrato, $request);


            if ($contrato->idtipoContrato == 8) {
                $salario = new Salario();
                $salario->valor = $request->input('sueldo');
            } else {
                $salario = Salario::find($contrato->salario_id);

                if (!$salario) {
                    throw new \Exception("No se encontró el salario correspondiente al contrato", 505);
                }
            }

            if ($contrato->periodoPago == 30) {
                $this->storePagosPeriodoMensualExtension($contrato, $request, $transaccion);
            } elseif ($contrato->periodoPago == 15) {
                $this->storePagosPeriodoQuincenalExtension($contrato, $request, $transaccion);
            }

            // Cambia de estado al contrato anterior y sus transacciones
            $contratoAntiguo = Contract::with(['transacciones', 'transacciones.pago'])->find($idContrato);

            if ($contratoAntiguo) {
                $contratoAntiguo->idEstado = Status::ID_ADICION_CONTRATO;
                $contratoAntiguo->save();

                $transacciones = $contratoAntiguo->transacciones;

                foreach ($transacciones as $transaccion) {
                    $transaccion->update(['idEstado' => Status::ID_ADICION_CONTRATO]);
                }
            }

            //actualiza la fecha de acceso a la aplicacion
            $activationUser = ActivationCompanyUser::where('user_id', $user->id)->first();

            if ($activationUser) {
                $activationUser->fechaFin = $fechaFinalContrato;

                $activationUser->save();
            } else {
                throw new \Exception("No se encontró el usuario", 404);
            }

            $activationUser->assignRole($request->input('rol'));
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('Error en storeContrato (creación de contrato)', [
                'message' => $th->getMessage(),
            ]);
            return response()->json([
                'message' => 'No se pudo crear el contrato. Intenta nuevamente.',
            ], 400);
        }
    }



    /**
     * Almacena pagos mensuales para la extensión de un contrato.
     *
     * Esta función crea y almacena pagos mensuales asociados a una transacción de extensión
     * de contrato. Se generan pagos para cada mes completo entre la fecha de inicio y la fecha
     * de finalización del contrato, excluyendo el mes de la fecha de inicio si está entre los días
     * 25 y 30 para evitar duplicados.
     *
     * @param \App\Models\Contract
     * @param \Illuminate\Http\Request
     * @param \App\Models\Transaccion
     * @return void
     */
    private function storePagosPeriodoMensualExtension($contrato, $request, $transaccion)
    {

        $fechaInicio = Carbon::parse($contrato->fechaContratacion)->startOfMonth();
        $fechaFin = Carbon::parse($contrato->fechaFinalContrato);
        $mesesContrato = $fechaInicio->diffInMonths($fechaFin);


        for ($i = 0; $i  <= $mesesContrato; $i++) {
            $pago = new Pago();
            $pago->idMedioPago = 4;
            $pago->valor = $request->input('sueldo');
            $pago->idTransaccion = $transaccion->id;
            $pago->idEstado = Status::ID_PENDIENTE;

            $fechaInicialContraro = $contrato->fechaContratacion;

            // Evitar crear pago para el mes correspondiente a la fecha inicial si está entre los días 25 y 31
            if ($i == 0 && $fechaInicialContraro->day >= 25 && $fechaInicialContraro->day <= 31) {
                continue;
            }

            $fechaPago = $fechaInicio->copy()->addMonths($i)->day(30);

            if ($fechaPago->month == 2) {
                $pago->fechaPago = $fechaPago->day(28)->format('Y-m-d');
            } elseif ($fechaPago->day == 1 && $fechaPago->month == 3) {
                $pago->fechaPago = $fechaPago->day(28)->subMonth()->format('Y-m-d');
            } else {
                $pago->fechaPago = $fechaPago->format('Y-m-d');
            }

            if (Carbon::parse($pago->fechaPago)->lt(Carbon::now())) {
                $pago->idEstado = 5;
                $pago->fechaReg = $pago->fechaPago;
                $pago->observacion = "Registro migratorio";
            }

            $pago->save();
        }
    }



    /**
     * Almacena pagos quincenales para la extensión de un contrato.
     *
     * Esta función crea y almacena pagos quincenales asociados a una transacción de extensión
     * de contrato. Se generan dos pagos para cada mes completo entre la fecha de inicio y la fecha
     * de finalización del contrato, excluyendo el mes de la fecha de inicio si está entre los días
     * 25 y 30 para evitar duplicados. Los pagos se programan para los días 15 y 30 (o 28 en febrero).
     *
     * @param \App\Models\Contract
     * @param \Illuminate\Http\Request
     * @param \App\Models\Transaccion
     * @return void
     */
    private function storePagosPeriodoQuincenalExtension($contrato, $request, $transaccion)
    {
        $fechaInicio = Carbon::parse($contrato->fechaContratacion)->startOfMonth();
        $fechaFin = Carbon::parse($contrato->fechaFinalContrato);
        $mesesContrato = $fechaInicio->diffInMonths($fechaFin);

        for ($i = 0; $i  <= $mesesContrato; $i++) {
            $pago = new Pago();
            $pago->idMedioPago = 4;
            $pago->valor = $request->input('sueldo');
            $pago->idTransaccion = $transaccion->id;
            $pago->idEstado = Status::ID_PENDIENTE;

            $fechaInicialContraro = $contrato->fechaContratacion;

            if ($i == 0 && $fechaInicialContraro->day >= 10 && $fechaInicialContraro->day <= 15) {
                continue;
            }


            // Pago 1 - Día 15
            $fechaPago1 = $fechaInicio->copy()->addMonths($i)->day(15);
            if ($fechaPago1->month == 2) {
                $pago->fechaPago = $fechaPago1->day(28)->format('Y-m-d');
            } else {
                $pago->fechaPago = $fechaPago1->format('Y-m-d');
            }

            if (Carbon::parse($pago->fechaPago)->lt(Carbon::now())) {
                $pago->idEstado = 5;
                $pago->idMedioPago = 4;
                $pago->fechaReg = $pago->fechaPago;
                $pago->observacion = "Registro migratorio";
            }
            $pago->save();

            if ($i == 0 && $fechaInicialContraro->day >= 25 && $fechaInicialContraro->day <= 30) {
                continue;
            }

            // Pago 2 - Día 30 o 28 en febrero
            $fechaPago2 = $fechaInicio->copy()->addMonths($i)->day(30);
            if ($fechaPago2->month == 2) {
                $pago = new Pago();
                $pago->idMedioPago = 4;
                $pago->valor = $request->input('sueldo');
                $pago->idTransaccion = $transaccion->id;
                $pago->idEstado = Status::ID_PENDIENTE;
                $pago->fechaPago = $fechaPago2->day(28)->format('Y-m-d');
            } else {
                $pago = new Pago();
                $pago->valor = $request->input('sueldo');
                $pago->idMedioPago = 4;
                $pago->idTransaccion = $transaccion->id;
                $pago->idEstado = Status::ID_PENDIENTE;
                $pago->fechaPago = $fechaPago2->format('Y-m-d');
            }

            if (Carbon::parse($pago->fechaPago)->lt(Carbon::now())) {
                $pago->idEstado = 5;
                $pago->idMedioPago = 4;
                $pago->fechaReg = $pago->fechaPago;
                $pago->observacion = "Registro migratorio";
            }

            $pago->save();
        }
    }



    /**
     * Envía un correo electrónico de notificación sobre la extensión de un contrato.
     *
     * Esta función se encarga de enviar un correo electrónico informativo al empleado cuyo contrato
     * ha sido extendido. Incluye detalles sobre el acceso a la plataforma y agradecimientos por su
     * compromiso continuo con la empresa.
     *
     * @param \App\Models\Person $persona - La entidad de persona asociada al contrato.
     * @return void
     */
    private function sendCorreoExtensionContrato($persona)
    {
        $correoPersona = $persona->email;
        $url = 'https://admin.virtualt.org/#/login';
        $subject = "Extensión de Contrato en Virtual Technology";
        $message = "Estimado(a) {$persona->nombre1},

        Nos complace informarte que se ha realizado una extensión de tu contrato en Virtual Technology. A continuación, te proporcionamos los detalles:

        - **Acceso a la Plataforma:**
          Para acceder a tu cuenta, sigue estos pasos:
          1. Ingresa a la plataforma en [Enlace de Acceso]($url).
          2. Utiliza la siguiente información de inicio de sesión:
             - Correo electrónico: $correoPersona
             - Contraseña: {$persona->identificacion}

        ¡Agradecemos tu continuo compromiso con Virtual Technology! Estamos aquí para cualquier pregunta o asistencia que necesites.

        Atentamente,
        El equipo de Virtual Technology";

        $mailService = new MailService($subject, $message);
        Mail::to($correoPersona)->send($mailService);
    }



    /**
     * Almacena una notificación de extensión de contrato en la base de datos.
     *
     * Esta función crea y guarda una notificación en la base de datos para informar al empleado
     * sobre la extensión de su contrato. La notificación incluye detalles relevantes como el número
     * de identificación, la fecha de extensión y un mensaje de agradecimiento.
     *
     * @param \App\Models\Person
     * @return void
     */
    private function storeNotficationExtensionContrato($persona)
    {
        $subject = "Extensión de Contrato en Virtual Technology";
        $message2 = "Estimado(a) {$persona->nombre1},\n\n";
        $message2 .= "Nos complace informarte que hemos realizado una extensión de tu contrato en Virtual Technology. ";
        $message2 .= "A continuación, te proporcionamos los detalles:\n\n";
        $message2 .= "Número de identificación: {$persona->identificacion}\n";
        $message2 .= "Fecha de extensión: " . now()->format('Y-m-d H:i:s') . "\n\n";
        $message2 .= "Agradecemos tu compromiso y dedicación a lo largo de tu tiempo con nosotros. ";
        $message2 .= "Estamos emocionados de continuar trabajando contigo.\n\n";
        $message2 .= "Atentamente,\n";
        $message2 .= "El equipo de Virtual Technology.\n\n";


        $notification = new Notificacion();
        $notification->estado_id = Status::ID_ACTIVE;
        $notification->asunto = $subject;
        $notification->mensaje =  $message2;
        $notification->route =  '';
        $notification->idUsuarioReceptor = $persona->id;
        $notification->idUsuarioRemitente =  auth()->user()->id;
        $notification->idEmpresa = KeyUtil::idCompany();
        $notification->idTipoNotificacion = 1;
        $notification->fecha = Carbon::now()->toDateTimeString();
        $notification->hora = Carbon::now()->format('H:i:s');
        $notification->save();
    }



    /**
     * Almacena la trazabilidad de un contrato después de una extensión.
     *
     * Esta función crea y guarda un registro de trazabilidad asociado a la extensión de un contrato.
     * Incluye detalles sobre la extensión, como una observación y la URL del archivo relacionado.
     *
     * @param \App\Models\Contract
     * @param \Illuminate\Http\Request
     * @return void
     */
    private function storeTrazabilidadContrato($idContrato, $request)
    {
        $archivoContrato = new ArchivoContrato();
        $archivoContrato->idContrato = $idContrato;
        $archivoContrato->observacion = 'Extensión de contrato';
        $archivoContrato->fecha = now();
        $archivoContrato->url = $this->storeArchivoContrato($request);

        $archivoContrato->save();
    }


    public function getContratoByIdentificacionActive($identificacion)
    {

        $contratos = Contract::with('persona.ciudadNac', 'tipoContrato')
            ->whereHas("persona", function ($q) use ($identificacion) {
                $q->where('identificacion', '=', $identificacion);
            })
            ->where('idEstado', Status::ID_ACTIVE)
            ->first();

        return response()->json($contratos);
    }


    /**
     * Obtiene los bancos.
     *@return \Illuminate\Http\JsonResponse
     */

    public function bancos()
    {
        $bancos = Banco::all();
        return response()->json($bancos);
    }



    public function storeBanco(Request $request)
    {
        $banco = new Banco();
        $banco->nombre = $request->input('nombre');

        $banco->save();
        return response()->json($banco, 201);
    }


    /**
     * Obtiene los tipo de terminacion de contrato.
     *@return \Illuminate\Http\JsonResponse
     */

    public function tiposTerminacionContrato()
    {
        $tiposTerminacion = TipoTerminacionContrato::all();
        return response()->json($tiposTerminacion);
    }


    public function getActividadesRiesgoProfesional()
    {
        $riesgos = ActividadRiesgoProfesional::all();
        return response()->json($riesgos);
    }



    public function storeActividadeRiesgoProfesional(Request $request)
    {
        $riesgos = new ActividadRiesgoProfesional();
        $riesgos->codigo = $request->input('codigo');
        $riesgos->clase = $request->input('clase');
        $riesgos->save();
        return response()->json($riesgos, 201);
    }


    public function updateEntidadSeguridadSocial(Request $request, $id)
    {
        try {

            $validated = $request->validate([
                'tipo' => 'required|string|in:PENSION,SALUD,ARL,CAJA COMPENSACION,CESANTIAS',
            ]);


            $contrato = Contract::find($id);

            if (!$contrato) {
                return response()->json([
                    'message' => 'Contrato no encontrado.'
                ], 404);
            }


            if ($validated['tipo'] === 'PENSION') {
                $contrato->idPension = $request->input('entidad_id');
                $contrato->idPensionMovilidad = $request->input('entidad_id');

                $novedad = new Novedad();
                $novedad->tipo = 'TRASLADO A OTRA ADMINISTRADORA DE PENSIONES';
                $novedad->descripcion = 'Empleado trasladado a otra administradora de pensiones';
                $novedad->idContrato = $contrato->id;
                $novedad->estado = 'LIQUIDADO';
                $novedad->fechaInicial = now();
                $novedad->save();
            } elseif ($validated['tipo'] === 'SALUD') {
                $contrato->idSalud = $request->input('entidad_id');
                $contrato->idSaludMovilidad = $request->input('entidad_id');

                $novedad = new Novedad();
                $novedad->tipo = 'TRASLADO A OTRA EPS O EOC';
                $novedad->descripcion = 'Empleado trasladado a otra EPS';
                $novedad->idContrato = $contrato->id;
                $novedad->estado = 'LIQUIDADO';
                $novedad->fechaInicial = now();
                $novedad->save();
            } elseif ($validated['tipo'] === 'ARL') {
                $contrato->idArl = $request->input('entidad_id');
            } elseif ($validated['tipo'] === 'CAJA COMPENSACION') {
                $contrato->idCajaCompensacion = $request->input('entidad_id');
            } elseif ($validated['tipo'] === 'CESANTIAS') {
                $contrato->idCesantias = $request->input('entidad_id');
            }

            $contrato->save();

            return response()->json([
                'message' => 'Entidad actualizada correctamente.',
                'contrato' => $contrato
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la entidad.',
                'error' => $e->getMessage()
            ], 500);
        }
    }






    /**
     * Lista de opciones de `formaPago` definidas en el enum existente (sin crear registros en BD).
     */
    public function formasPagoContrato()
    {
        return response()->json(TypePaymentMethodContract::getValues());
    }

    public function udapteContrato(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $contrato = Contract::find($id);

            if (!$contrato) {
                DB::rollBack();
                return response()->json(['error' => 'Contrato no encontrado'], 404);
            }

            $prevFechaIni = $contrato->fechaContratacion;
            $prevFechaFin = $contrato->fechaFinalContrato;

            // Solo actualizar campos que vienen en el request
            if ($request->has('idtipoContrato')) {
                $contrato->idtipoContrato = $request->input('idtipoContrato');
            }

            if ($request->has('fechaContratacion')) {
                $fechaInicio = Carbon::parse($request->input('fechaContratacion'));
                $contrato->fechaContratacion = $fechaInicio;
            }

            if ($request->has('fechaFinalContrato')) {
                if ($contrato->idtipoContrato == 6) {
                    $contrato->fechaFinalContrato = null;
                } else {
                    $fechaFinalContrato = Carbon::parse($request->input('fechaFinalContrato'))->format('Y-m-d');
                    $contrato->fechaFinalContrato = $fechaFinalContrato;
                }
            }

            if ($request->has('valorTotalContrato')) {
                $contrato->valorTotalContrato = $request->input('valorTotalContrato');
            }

            // No pisar salario_id con null si el front envía la clave sin valor
            if ($request->filled('salario_id')) {
                $contrato->salario_id = $request->input('salario_id');
            }

            if ($request->has('periodoPago')) {
                $pp = $request->input('periodoPago');
                // BD: entero (10=semanal, 15=quincenal, 30=mensual). Compat. con textos del front antiguo.
                if (is_string($pp) && $pp !== '' && !is_numeric($pp)) {
                    $map = [
                        'MENSUAL' => 30,
                        'QUINCENAL' => 15,
                        'SEMANAL' => 10,
                        'DIARIO' => 1,
                    ];
                    $key = strtoupper(trim($pp));
                    if (isset($map[$key])) {
                        $contrato->periodoPago = $map[$key];
                    }
                } elseif ($pp !== null && $pp !== '') {
                    $contrato->periodoPago = (int) $pp;
                }
            }

            // --- Datos de supervisión y forma de pago ---
            if ($request->filled('formaPago')) {
                $formaPago = mb_strtoupper(trim((string) $request->input('formaPago')), 'UTF-8');

                // Compatibilidad histórica de columna (camelCase vs snake_case)
                if (Schema::hasColumn('contrato', 'formaDePago')) {
                    $contrato->formaDePago = $formaPago;
                } elseif (Schema::hasColumn('contrato', 'formaPago')) {
                    $contrato->formaPago = $formaPago;
                } elseif (Schema::hasColumn('contrato', 'forma_pago')) {
                    $contrato->forma_pago = $formaPago;
                }
            }

            if (Schema::hasColumn('contrato', 'supervisorContrato') && $request->has('supervisorContrato')) {
                $contrato->supervisorContrato = $request->input('supervisorContrato');
            }

            if (Schema::hasColumn('contrato', 'cargoSupervisor') && $request->has('cargoSupervisor')) {
                $contrato->cargoSupervisor = $request->input('cargoSupervisor');
            }

            if ($request->has('objetoContrato')) {
                $contrato->objetoContrato = $request->input('objetoContrato');
            }

            if ($request->has('observacion')) {
                $contrato->observacion = $request->input('observacion');
            }

            if ($request->has('perfilProfesional')) {
                $contrato->perfilProfesional = $request->input('perfilProfesional') ?: 'N/A';
            }

            if ($request->has('otrosi')) {
                $contrato->otrosi = $request->input('otrosi');
            }

            if (Schema::hasColumn('contrato', 'numeroDocumentoContrato') && $request->has('numeroDocumentoContrato')) {
                $v = $request->input('numeroDocumentoContrato');
                $contrato->numeroDocumentoContrato = $v !== null && $v !== '' ? trim((string) $v) : null;
            }

            if (Schema::hasColumn('contrato', 'numeroContrato') && $request->has('numeroContrato')) {
                $v = $request->input('numeroContrato');
                $contrato->numeroContrato = $v !== null && trim((string) $v) !== '' ? trim((string) $v) : null;
            }

            if ($request->has('idEstado')) {
                $contrato->idEstado = $request->input('idEstado');
            }

            if ($request->has('idPension')) {
                $contrato->idPension = $request->input('idPension');
            }

            if ($request->has('idArl')) {
                $contrato->idArl = $request->input('idArl');
            }

            if ($request->has('idSalud')) {
                $contrato->idSalud = $request->input('idSalud');
            }

            if ($request->has('idCajaCompensacion')) {
                $contrato->idCajaCompensacion = $request->input('idCajaCompensacion');
            }

            if ($request->has('idCesantias')) {
                $contrato->idCesantias = $request->input('idCesantias');
            }

            if ($request->has('tipoCuentaBancaria')) {
                $contrato->tipoCuentaBancaria = $request->input('tipoCuentaBancaria');
            }

            if ($request->has('tipoCotizante')) {
                $contrato->tipoCotizante = $request->input('tipoCotizante');
            }

            if ($request->has('numeroCuentaBancaria')) {
                $contrato->numeroCuentaBancaria = $request->input('numeroCuentaBancaria');
            }

            if ($request->has('idTipoCotizante')) {
                $contrato->idTipoCotizante = $request->input('idTipoCotizante');
            }

            if ($request->has('idSubTipoCotizante')) {
                $contrato->idSubTipoCotizante = $request->input('idSubTipoCotizante');
            }

            if ($request->has('idBanco')) {
                $contrato->idBanco = $request->input('idBanco');
            }

            if ($request->has('tipoSalario')) {
                $contrato->tipoSalario = $request->input('tipoSalario');
            }

            if ($request->has('idTarifaRiesgo')) {
                $contrato->idTarifaRiesgo = $request->input('idTarifaRiesgo');
            }

            if ($request->has('idActividadRiesgo')) {
                $contrato->idActividadRiesgo = $request->input('idActividadRiesgo');
            }

            if ($request->has('idArea')) {
                $contrato->idArea = $request->input('idArea');
            }

            if ($request->has('idGrupoNomina')) {
                $contrato->idGrupoNomina = $request->input('idGrupoNomina');
            }

            if ($request->has('idCentroFormacion')) {
                $contrato->idCentroFormacion = $request->input('idCentroFormacion') ?: null;
            }

            if ($request->has('horasmes')) {
                $contrato->horasmes = $request->input('horasmes');
            }

            if ($request->has('idNivelEducativo')) {
                $contrato->idNivelEducativo = $request->input('idNivelEducativo');
            }

            // Actualizar áreas de conocimiento
            if ($request->has('areasConocimiento') && is_array($request->input('areasConocimiento'))) {
                // Asegura unicidad y tipo entero antes del sync para evitar constraint unique en el pivot.
                $areas = $request->input('areasConocimiento');
                $areas = array_map(function ($value) {
                    return (int) $value;
                }, $areas);
                $areas = array_values(array_unique($areas));
                $contrato->areasConocimiento()->sync($areas);
            }

            // Actualizar programas
            if ($request->has('programas') && is_array($request->input('programas'))) {
                $contrato->programas()->sync($request->input('programas'));
            }

            if ($request->has('observacionPreocupacional')) {
                $observacionTexto = trim($request->input('observacionPreocupacional'));

                if (!empty($observacionTexto)) {
                    $observacionPreocupacional = new ObservacionPreocupacional();
                    $observacionPreocupacional->idPersona = $contrato->idpersona;
                    $observacionPreocupacional->observacion = $observacionTexto;
                    $observacionPreocupacional->save();
                }
            }

            $normFechaContrato = static function ($d) {
                if ($d === null || $d === '') {
                    return null;
                }
                try {
                    return Carbon::parse($d)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            };
            $datesChanged =
                $normFechaContrato($prevFechaIni) !== $normFechaContrato($contrato->fechaContratacion)
                || $normFechaContrato($prevFechaFin) !== $normFechaContrato($contrato->fechaFinalContrato);

            $contrato->save();





            // Solo crear vacación si cambió la fecha de inicio y el tipo de contrato lo requiere
            $fechaInicioChanged = $normFechaContrato($prevFechaIni) !== $normFechaContrato($contrato->fechaContratacion);
            if ($fechaInicioChanged && $request->has('fechaContratacion') && in_array($contrato->idtipoContrato, [6, 7])) {
                $fechaInicio = Carbon::parse($request->input('fechaContratacion'));
                $vacion = new Vacacion();
                $vacion->idContrato = $contrato->id;
                $vacion->periodo = $fechaInicio->copy()->addYear()->year;
                $vacion->estado = 'PENDIENTE';
                $vacion->save();
            }

            // Solo sincronizar usuario / activación / salario cuando cambian fechas, salario, rol o centro.
            // El front suele reenviar las mismas fechas en cada guardado: sin comparar, siempre fallaría salario/activación.
            $needsUserActivationSync =
                $datesChanged
                || $request->has('sueldo')
                || $request->filled('rol')
                || ($request->has('idCentroFormacion') && $request->filled('idCentroFormacion'));

            if ($needsUserActivationSync) {
                $user = User::where('idpersona', $contrato->idpersona)->first();

                if (!$user) {
                    throw new \Exception("No se encontro la persona", 505);
                }

                $persona = Person::find($contrato->idpersona);

                if (!$persona) {
                    throw new \Exception("No se encontró la persona", 505);
                }

                if ($contrato->idtipoContrato == 8) {
                    $salario = new Salario();
                    $salario->valor = $request->input('sueldo');
                } else {
                    $salario = Salario::find($contrato->salario_id);
                    if (!$salario) {
                        throw new \Exception("No se encontró el salario correspondiente al contrato", 505);
                    }
                    if ($request->has('sueldo')) {
                        $salario->valor = $request->input('sueldo');
                        $salario->save();
                    }
                }

                $activationUser = ActivationCompanyUser::where('user_id', $user->id)->first();

                if (!$activationUser) {
                    DB::rollBack();
                    return response()->json(['error' => 'Activación del usuario no encontrada'], 404);
                }

                // Solo actualizar fechas de activación si se actualizó fechaContratacion
                if ($request->has('fechaContratacion')) {
                    $fechaInicio = Carbon::parse($request->input('fechaContratacion'));
                    $activationUser->fechaInicio = $fechaInicio;

                    if ($contrato->idtipoContrato == 6) {
                        $activationUser->fechaFin = date('Y-m-d', strtotime($fechaInicio . ' + 3 years'));
                    } else {
                        if ($request->has('fechaFinalContrato')) {
                            $fechaFinalContrato = Carbon::parse($request->input('fechaFinalContrato'))->format('Y-m-d');
                            $activationUser->fechaFin = $fechaFinalContrato;
                        }
                    }
                }

                $activationUser->state_id = Status::ID_ACTIVE;

                if ($request->filled('rol')) {
                    $activationUser->assignRole($request->input('rol'));
                }

                $activationUser->saveWithCompany();

                // Actualizar el centro de formación del usuario si se proporciona
                if ($request->has('idCentroFormacion') && $request->input('idCentroFormacion')) {
                    $user->idCentroFormacion = $request->input('idCentroFormacion');
                    $user->save();
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error en udapteContrato', [
                'contrato_id' => $id,
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            $payload = [
                'message' => 'No se pudo actualizar el contrato. Intente nuevamente.',
            ];
            if (config('app.debug')) {
                $payload['debug'] = $th->getMessage();
            }

            return response()->json($payload, 500);
        }

        return response()->json($contrato, 201);
    }







    public function updatePersonaContrato(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $persona = Person::find($id);

            if (!$persona) {
                return response()->json(['error' => 'Persona no encontrada'], 404);
            }

            // Si solo se está actualizando la foto, no validar ni actualizar otros campos
            if ($request->hasFile('rutaFotoFile') && $request->allFiles()['rutaFotoFile'] && count($request->all()) === 1) {
                $persona->rutaFoto = $this->storeLogoPersona($request);
                $persona->save();
                DB::commit();
                return response()->json($persona, 200);
            }

            // Validar email e identificación solo si vienen en el request
            $email = $request->has('email') ? trim($request->input('email')) : null;
            $identificacion = $request->has('identificacion') ? trim($request->input('identificacion')) : null;

            if ($email !== null || $identificacion !== null) {
                $personaExistente = Person::where('id', '<>', $id)
                    ->where(function ($query) use ($email, $identificacion) {
                        if ($email !== null && $email !== '') {
                            $query->orWhere('email', $email);
                        }
                        if ($identificacion !== null && $identificacion !== '') {
                            $query->orWhere('identificacion', $identificacion);
                        }
                    })
                    ->first();

                if ($personaExistente) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'El email o la identificación ya pertenecen a otra persona.'
                    ], 409);
                }

                $user = User::where('idpersona', $persona->id)->first();

                if ($email !== null && $email !== '') {
                    $emailDuplicadoUser = User::where('email', $email)
                        ->when($user, function ($q) use ($user) {
                            return $q->where('id', '<>', $user->id);
                        })
                        ->first();

                    if ($emailDuplicadoUser) {
                        DB::rollBack();
                        return response()->json([
                            'error' => 'El email ya está registrado en un usuario del sistema.'
                        ], 409);
                    }
                }
            }

            // Actualizar solo los campos que vienen en el request
            if ($request->has('fechaNac')) {
                $persona->fechaNac = $request->input('fechaNac');
            }
            
            if ($request->has('idtipoIdentificacion')) {
                $idTipoIdentificacion = $request->input('idtipoIdentificacion');
                if ($idTipoIdentificacion !== null && $idTipoIdentificacion !== '' && $idTipoIdentificacion !== 0) {
                    $persona->idTipoIdentificacion = $idTipoIdentificacion;
                }
            }
            
            if ($identificacion !== null) {
                $persona->identificacion = $identificacion;
            }
            
            if ($request->has('nombre1')) {
                $persona->nombre1 = $request->input('nombre1');
            }
            if ($request->has('nombre2')) {
                $persona->nombre2 = $request->input('nombre2');
            }
            if ($request->has('apellido1')) {
                $persona->apellido1 = $request->input('apellido1');
            }
            if ($request->has('apellido2')) {
                $persona->apellido2 = $request->input('apellido2');
            }
            if ($request->has('idciudadNac')) {
                $persona->idCiudadNac = $request->input('idciudadNac');
            }
            if ($request->has('celular')) {
                $persona->celular = $request->input('celular');
            }
            if ($email !== null) {
                $persona->email = $email;
            }
            if ($request->has('direccion')) {
                $persona->direccion = $request->input('direccion');
            }
            if ($request->has('idciudadUbicacion')) {
                $persona->idCiudadUbicacion = $request->input('idciudadUbicacion');
            }
            if ($request->has('ciudadExpedicion') || $request->has('idciudadExpedicion')) {
                $raw = $request->input('ciudadExpedicion') ?? $request->input('idciudadExpedicion');
                $persona->ciudadExpedicion = $raw !== null && $raw !== '' ? (int) $raw : null;
            }
            if ($request->has('telefonoFijo')) {
                $persona->telefonoFijo = $request->input('telefonoFijo');
            }
            if ($request->has('sexo')) {
                $persona->sexo = $request->input('sexo');
            }
            if ($request->has('rh')) {
                $persona->rh = $request->input('rh');
            }

            if ($request->hasFile('rutaFotoFile')) {
                $persona->rutaFoto = $this->storeLogoPersona($request);
            }

            $persona->save();

            $user = User::where('idpersona', $persona->id)->first();
            if ($user && $email !== null && $email !== '') {
                $user->email = $email;
                $user->save();
            }

            DB::commit();
            return response()->json($persona, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error en updatePersonaContrato', [
                'persona_id' => $id,
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            $payload = [
                'message' => 'No se pudo actualizar la información de la persona.',
            ];
            if (config('app.debug')) {
                $payload['debug'] = $th->getMessage();
            }

            return response()->json($payload, 500);
        }
    }




    public function updateDocumentosContrato(Request $request)
    {
        $idContrato = $request->input('idContrato');
        $tipoId = $request->input('idAsignacionTipoDocumentoProceso');


        $documentoContrato = DocumentoContrato::where('idContrato', $idContrato)
            ->where('idAsignacionTipoDocumentoProceso', $tipoId)
            ->first();

        if (!$documentoContrato) {
            $documentoContrato = new DocumentoContrato();
            $documentoContrato->idContrato = $idContrato;
            $documentoContrato->idAsignacionTipoDocumentoProceso = $tipoId;
        }

        $documentoContrato->ruta = $this->storeRutaDocumento($request);
        $documentoContrato->fechaCarga = now();

        $documentoContrato->save();

        return response()->json($documentoContrato, 200);
    }

    /**
     * Obtiene todas las áreas de conocimiento disponibles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAreasConocimiento()
    {
        $areas = AreaConocimiento::orderBy('nombreAreaConocimiento', 'asc')->get();
        return response()->json($areas);
    }

    /**
     * Obtiene las áreas de conocimiento para asinar a una nueva competencia
     * teniendo en cuenta el programa y nivel educativo.
     *
     * @param int $idPrograma
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAreasConocimientoPrograma(int $idPrograma)
    {
        $programa = Programa::find($idPrograma);

        if (!$programa) {
            return response()->json([
                'message' => 'No se encontró el programa',
                'data' => []
            ], 404);
        }

        $areas = AreaConocimiento::whereHas('programas', function ($query) use ($idPrograma) {
            $query->where('idPrograma', $idPrograma);
        })->orderBy('nombreAreaConocimiento', 'asc')->get();

        return response()->json([
            'message' => 'Áreas obtenidas correctamente',
            'data' => $areas
        ], 200);
    }

    /**
     * Obtiene las áreas de conocimiento de múltiples programas.
     * Retorna todas las áreas únicas asociadas a los programas especificados.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAreasConocimientoProgramas(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idProgramas' => 'required|array|min:1',
                'idProgramas.*' => 'integer|exists:programa,id'
            ]);

            $idProgramas = $validated['idProgramas'];

            // Obtener todas las áreas de conocimiento asociadas a los programas especificados
            $areas = AreaConocimiento::whereHas('programas', function ($query) use ($idProgramas) {
                $query->whereIn('idPrograma', $idProgramas);
            })
            ->orderBy('nombreAreaConocimiento', 'asc')
            ->get();

            return response()->json([
                'message' => 'Áreas de conocimiento obtenidas correctamente',
                'data' => $areas
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al obtener áreas de conocimiento por programas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al obtener áreas de conocimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea una nueva área de conocimiento y la asocia a los programas especificados.
     * Valida que no exista duplicado en ninguno de los programas.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAreaConocimiento(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombreAreaConocimiento' => 'required|string|max:255',
                'idProgramas' => 'nullable|array',
                'idProgramas.*' => 'integer|exists:programa,id'
            ]);

            $nombreArea = trim($validated['nombreAreaConocimiento']);
            $idProgramas = $validated['idProgramas'] ?? [];

            // Buscar si el área ya existe (por nombre)
            $areaExistente = AreaConocimiento::where('nombreAreaConocimiento', $nombreArea)->first();

            // Si el área no existe, crearla
            if (!$areaExistente) {
                $areaExistente = AreaConocimiento::create([
                    'nombreAreaConocimiento' => $nombreArea
                ]);
            }

            // Verificar qué programas ya tienen el área asociada y cuáles no
            $programasConArea = [];
            $programasSinArea = [];
            
            if (!empty($idProgramas)) {
                // Obtener programas donde el área ya existe
                $programasExistentes = DB::table('asignacionAreaConocimientoPrograma')
                    ->where('idAreaConocimiento', $areaExistente->id)
                    ->whereIn('idPrograma', $idProgramas)
                    ->join('programa', 'asignacionAreaConocimientoPrograma.idPrograma', '=', 'programa.id')
                    ->select('programa.nombrePrograma', 'programa.id')
                    ->get();

                $programasConArea = $programasExistentes->pluck('nombrePrograma')->toArray();
                $idsProgramasConArea = $programasExistentes->pluck('id')->toArray();
                
                // Programas donde NO existe el área
                $idsProgramasSinArea = array_diff($idProgramas, $idsProgramasConArea);
                
                // Obtener nombres de programas donde no existe
                if (!empty($idsProgramasSinArea)) {
                    $programasSinArea = DB::table('programa')
                        ->whereIn('id', $idsProgramasSinArea)
                        ->pluck('nombrePrograma')
                        ->toArray();
                }

                // Asociar el área solo a los programas donde NO existe
                foreach ($idsProgramasSinArea as $idPrograma) {
                    DB::table('asignacionAreaConocimientoPrograma')->insert([
                        'idAreaConocimiento' => $areaExistente->id,
                        'idPrograma' => $idPrograma,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Construir mensaje según el caso
            $message = 'Área de conocimiento procesada correctamente';
            $warnings = [];
            
            if (!empty($programasConArea) && !empty($programasSinArea)) {
                // El área ya existía en algunos programas pero se asoció a otros
                $message = 'Área de conocimiento asociada a los programas seleccionados';
                $warnings[] = "El área ya existía en: " . implode(', ', $programasConArea);
                $warnings[] = "Se asoció a: " . implode(', ', $programasSinArea);
            } elseif (!empty($programasConArea) && empty($programasSinArea)) {
                // El área ya existe en todos los programas seleccionados
                return response()->json([
                    'message' => "Área de conocimiento ya existe en todos los programas seleccionados: " . implode(', ', $programasConArea),
                    'error' => 'DUPLICADO_EN_PROGRAMA',
                    'data' => [
                        'area' => $areaExistente,
                        'programas' => $programasConArea
                    ]
                ], 409); // 409 Conflict
            } elseif (empty($programasConArea) && !empty($programasSinArea)) {
                // El área no existía en ningún programa, se creó y asoció
                $message = 'Área de conocimiento creada y asociada correctamente';
            }

            return response()->json([
                'message' => $message,
                'warnings' => $warnings,
                'data' => $areaExistente->load('programas')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear área de conocimiento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al crear área de conocimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todos los programas disponibles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProgramas()
    {
        try {
            // Obtener todos los programas sin filtrar por empresa
            $programas = Programa::with('nivel', 'tipoFormacion', 'estado')
                ->orderBy('nombrePrograma', 'asc')
                ->get();
            
            // Contar fichas reales para cada programa
            $programas->each(function ($programa) {
                $fichasCount = \App\Models\Ficha::whereHas('asignacion', function ($query) use ($programa) {
                    $query->where('idPrograma', $programa->id);
                })->count();
                $programa->fichas = $fichasCount;
            });
            
            return response()->json($programas);
        } catch (\Exception $e) {
            Log::error('Error en getProgramas: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error al obtener programas', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene los instructores (contratos) asignados a un programa específico.
     * Útil para asignar líderes de fichas.
     *
     * @param int $idPrograma
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInstructoresPorPrograma($idPrograma)
    {
        $instructores = Contract::with(['persona', 'nivelEducativo', 'areasConocimiento'])
            ->whereHas('programas', function ($query) use ($idPrograma) {
                $query->where('programa.id', $idPrograma);
            })
            ->where('idEstado', Status::ID_ACTIVE)
            ->where('idempresa', KeyUtil::idCompany())
            ->get();

        return response()->json($instructores);
    }
}
