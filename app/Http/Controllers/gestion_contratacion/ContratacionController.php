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
use App\Models\ContratoTransaccion;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Models\ActivationCompanyUser;
use App\Models\ActividadRiesgoProfesional;
use App\Models\AsignacionProcesoTipoDocumento;
use App\Models\Banco;
use App\Models\Nomina\Vacacion;
use App\Models\Novedad;
use App\Models\ObservacionPreocupacional;
use App\Models\TipoTerminacionContrato;

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
        $nombreProceso = $request->input('nombreProceso');
        $proceso = Proceso::where('nombreProceso', $nombreProceso)->first();

        if (!$proceso) {
            return response()->json(['error' => 'No se encontró el proceso especificado'], 404);
        }

        $tipoDocumentos = AsignacionProcesoTipoDocumento::with('proceso', 'tipoDocumento')
            ->where('idProceso', $proceso->id)
            ->get();

        if ($tipoDocumentos->isEmpty()) {
            return response()->json(['error' => 'No se encontraron tipos de documento asociados a este proceso'], 404);
        }

        return response()->json($tipoDocumentos);
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
        $roles = Rol::where('company_id', KeyUtil::idCompany())
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
            ->where('idPersona', $idPersona)
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
            DB::beginTransaction();
            $email = $request->input('email');
            $identificacion = $request->input('identificacion');
            $company_id = KeyUtil::idCompany();


            $personaExistente = Person::where('email', $email)
                ->orWhere('identificacion', $identificacion)
                ->first();

            if ($personaExistente) {
                return response()->json($personaExistente, 200);
            }

          
            $maxPersonId = Person::max('id') ?? 0;
            $maxUserId = User::max('id') ?? 0;
            $nextId = max($maxPersonId, $maxUserId) + 1;

            while (Person::where('id', $nextId)->exists() || User::where('id', $nextId)->exists()) {
                $nextId++;
            }

            $persona = new Person();
            $persona->id = $nextId;
            $persona->fechaNac = $request->input('fechaNac');
            $persona->idtipoIdentificacion = $request->input('idtipoIdentificacion');
            $persona->identificacion = $identificacion;
            $persona->nombre1 = $request->input('nombre1');
            $persona->nombre2 = $request->input('nombre2');
            $persona->apellido2 = $request->input('apellido2');
            $persona->apellido1 = $request->input('apellido1');
            $persona->idCiudadNac = $request->input('idciudadNac');
            $persona->celular = $request->input('celular');
            $persona->email = $email;
            $persona->direccion = $request->input('direccion');
            $persona->idCiudadUbicacion = $request->input('idciudadUbicacion');
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
            $tecero->idTipoIdentificacion = $persona->idtipoIdentificacion;
            $tecero->email = $persona->email;
            $tecero->save();

            DB::commit();
            return response()->json($persona, 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['error' => $th->getMessage()], 501);
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
            $contrato->idtipoContrato = $request->input('idtipoContrato');
            $contrato->fechaContratacion = $fechaInicio;

            if ($contrato->idtipoContrato == 6) {
                $contrato->fechaFinalContrato = null;
            } else {
                $fechaFinalContrato = Carbon::parse($request->input('fechaFinalContrato'))->format('Y-m-d');
                $contrato->fechaFinalContrato = $fechaFinalContrato;
            }

            $contrato->valorTotalContrato = $request->input('valorTotalContrato');
            $contrato->salario_id = $request->input('salario_id');
            $contrato->periodoPago = $request->input('periodoPago');
            $contrato->objetoContrato = $request->input('objetoContrato');
            $contrato->observacion = $request->input('observacion');
            $contrato->perfilProfesional = $request->input('perfilProfesional') ?: 'N/A';
            $contrato->otrosi = 'N';
            $contrato->idEstado = Status::ID_ACTIVE;

            $contrato->idPension = $request->input('idPension');
            $contrato->idArl = $request->input('idArl');
            $contrato->idSalud = $request->input('idSalud');

            $contrato->idCajaCompensacion = $request->input('idCajaCompensacion');
            $contrato->idCesantias = $request->input('idCesantias');
            $contrato->tipoCuentaBancaria = $request->input('tipoCuentaBancaria');
            $contrato->tipoCotizante  = $request->input('tipoCotizante');
            $contrato->numeroCuentaBancaria = $request->input('numeroCuentaBancaria');
            $contrato->idTipoCotizante = $request->input('idTipoCotizante');
            $contrato->idSubTipoCotizante = $request->input('idSubTipoCotizante');
            $contrato->idBanco = $request->input('idBanco');
            $contrato->tipoSalario = $request->input('tipoSalario');
            $contrato->idTarifaRiesgo = $request->input('idTarifaRiesgo');
            $contrato->idActividadRiesgo = $request->input('idActividadRiesgo');
            $contrato->idArea = $request->input('idArea');
            $contrato->idGrupoNomina = $request->input('idGrupoNomina');



            $observacionTexto = trim($request->input('observacionPreocupacional'));

            if (!empty($observacionTexto)) {
                $observacionPreocupacional = new ObservacionPreocupacional();
                $observacionPreocupacional->idPersona = $contrato->idpersona;
                $observacionPreocupacional->observacion = $observacionTexto;
                $observacionPreocupacional->save();
            }


            $contrato->save();

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

            $user = User::where('idPersona', $persona_id)->first();

            if (!$user) {
                throw new \Exception("No se encontro la persona", 505);
            }

            $persona = Person::find($persona_id);

            if (!$persona) {
                throw new \Exception("No se encontró la persona", 505);
            }

            $correoSend = $this->sendCorreoContrato($persona);

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

            // if ($contrato->idtipoContrato == 6) {
            //     // Si el contrato es del tipo indefinido, crea un pago en el mes actual
            //     $this->pagoContratoIndefinido($request, $transaccion);
            // } else {
            //     // Si no, procede con los pagos mensuales o quincenales
            //     if ($contrato->periodoPago == 30) {
            //         $this->storePagosPeriodoMensual($contrato, $request, $transaccion);
            //     } elseif ($contrato->periodoPago == 15) {
            //         $this->storePagosPeriodoQuincenal($contrato, $request, $transaccion);
            //     }
            // }


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
            return response()->json($th->getMessage(), 500);
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
     * 'transacciones.pago' y 'estado'. Ordena los resultados por estado activo y fecha de contratación.
     * Retorna un objeto JSON con la información de los contratos.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllContratos()
    {
        $contratos = Contract::with('persona', 'salario.rol', 'transacciones.pago', 'estado', 'area')
            ->where('idEstado', '!=', 14)
            ->orderByRaw('CASE WHEN idEstado = 13 THEN 2 WHEN idEstado = 2 THEN 1 ELSE 0 END')
            ->orderBy('fechaContratacion')
            ->get();

        return response()->json($contratos);
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
            'persona.CiudadNac',
            'persona.observacionesPreocupacionales',
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

            $user = User::where('idPersona', $persona_id)->first();

            if (!$user) {
                throw new \Exception("No se encontro la persona", 505);
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
            return response()->json($th->getMessage(), 500);
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
                'tipo' => 'required|string|in:PENSION,SALUD',
            ]);


            $contrato = Contract::find($id);

            if (!$contrato) {
                return response()->json([
                    'message' => 'Contrato no encontrado.'
                ], 404);
            }


            if ($validated['tipo'] === 'PENSION') {
                $contrato->idPensionMovilidad = $request->input('entidad_id');

                $novedad = new Novedad();
                $novedad->tipo = 'TRASLADO A OTRA ADMINISTRADORA DE PENSIONES';
                $novedad->descripcion = 'Empleado trasladado a otra administradora de pensiones';
                $novedad->idContrato = $contrato->id;
                $novedad->estado = 'LIQUIDADO';
                $novedad->fechaInicial = now();
                $novedad->save();
            } elseif ($validated['tipo'] === 'SALUD') {
                $contrato->idSaludMovilidad = $request->input('entidad_id');

                $novedad = new Novedad();
                $novedad->tipo = 'TRASLADO A OTRA EPS O EOC';
                $novedad->descripcion = 'Empleado trasladado a otra EPS';
                $novedad->idContrato = $contrato->id;
                $novedad->estado = 'LIQUIDADO';
                $novedad->fechaInicial = now();
                $novedad->save();
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






    public function udapteContrato(Request $request, $id)
    {



        $fechaInicio = Carbon::parse($request->input('fechaContratacion'));

        try {
            DB::beginTransaction();
            $contrato = Contract::find($id);

            if (!$contrato) {
                return response()->json(['error' => 'Contrato no encontrado'], 404);
            }



            $contrato->idtipoContrato = $request->input('idtipoContrato');
            $contrato->fechaContratacion = $fechaInicio;

            if ($contrato->idtipoContrato == 6) {
                $contrato->fechaFinalContrato = null;
            } else {
                $fechaFinalContrato = Carbon::parse($request->input('fechaFinalContrato'))->format('Y-m-d');
                $contrato->fechaFinalContrato = $fechaFinalContrato;
            }

            $contrato->valorTotalContrato = $request->input('valorTotalContrato');
            $contrato->salario_id = $request->input('salario_id');
            $contrato->periodoPago = $request->input('periodoPago');
            $contrato->objetoContrato = $request->input('objetoContrato');
            $contrato->observacion = $request->input('observacion');
            $contrato->perfilProfesional = $request->input('perfilProfesional') ?: 'N/A';
            $contrato->otrosi = 'N';
            $contrato->idEstado = Status::ID_ACTIVE;

            $contrato->idPension = $request->input('idPension');
            $contrato->idArl = $request->input('idArl');
            $contrato->idSalud = $request->input('idSalud');

            $contrato->idCajaCompensacion = $request->input('idCajaCompensacion');
            $contrato->idCesantias = $request->input('idCesantias');
            $contrato->tipoCuentaBancaria = $request->input('tipoCuentaBancaria');
            $contrato->tipoCotizante  = $request->input('tipoCotizante');
            $contrato->numeroCuentaBancaria = $request->input('numeroCuentaBancaria');
            $contrato->idTipoCotizante = $request->input('idTipoCotizante');
            $contrato->idSubTipoCotizante = $request->input('idSubTipoCotizante');
            $contrato->idBanco = $request->input('idBanco');
            $contrato->tipoSalario = $request->input('tipoSalario');
            $contrato->idTarifaRiesgo = $request->input('idTarifaRiesgo');
            $contrato->idActividadRiesgo = $request->input('idActividadRiesgo');
            $contrato->idArea = $request->input('idArea');
            $contrato->idGrupoNomina = $request->input('idGrupoNomina');



            $observacionTexto = trim($request->input('observacionPreocupacional'));

            if (!empty($observacionTexto)) {
                $observacionPreocupacional = new ObservacionPreocupacional();
                $observacionPreocupacional->idPersona = $contrato->idpersona;
                $observacionPreocupacional->observacion = $observacionTexto;
                $observacionPreocupacional->save();
            }


            $contrato->save();





            if (in_array($contrato->idtipoContrato, [6, 7])) {
                $vacion = new Vacacion();
                $vacion->idContrato = $contrato->id;
                $vacion->periodo = $fechaInicio->copy()->addYear()->year;
                $vacion->estado = 'PENDIENTE';
                $vacion->save();
            }

            $user = User::where('idpersona', $contrato->idpersona)->first();

            if (!$user) {
                throw new \Exception("No se encontro la persona", 505);
            }

            $persona = Person::find($contrato->idpersona);

            if (!$persona) {
                throw new \Exception("No se encontró la persona", 505);
            }



            // $transaccion = $this->storeTransaccionAsignacion($contrato->valorTotalContrato, $contrato->id);


            if ($contrato->idtipoContrato == 8) {
                $salario = new Salario();
                $salario->valor = $request->input('sueldo');
            } else {
                $salario = Salario::find($contrato->salario_id);
                if (!$salario) {
                    throw new \Exception("No se encontró el salario correspondiente al contrato", 505);
                }
            }



            $activationUser = ActivationCompanyUser::where('user_id', $user->id)->first();

            if (!$activationUser) {
                return response()->json(['error' => 'Activación del usuario no encontrada'], 404);
            }

            $activationUser->fechaInicio = $fechaInicio;

            if ($contrato->idtipoContrato == 6) {

                $activationUser->fechaFin = date('Y-m-d', strtotime($fechaInicio . ' + 3 years'));
            } else {
                $activationUser->fechaFin = $fechaFinalContrato;
            }

            $activationUser->state_id = Status::ID_ACTIVE;

            if ($request->filled('rol')) {
                $activationUser->assignRole($request->input('rol'));
            }

            $activationUser->saveWithCompany();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th->getMessage(), 500);
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

            $email = trim($request->input('email'));
            $identificacion = trim($request->input('identificacion'));

            $personaExistente = Person::where('id', '<>', $id)
                ->where(function ($query) use ($email, $identificacion) {
                    if ($email !== '') {
                        $query->orWhere('email', $email);
                    }
                    if ($identificacion !== '') {
                        $query->orWhere('identificacion', $identificacion);
                    }
                })
                ->first();

            if ($personaExistente) {
                return response()->json([
                    'error' => 'El email o la identificación ya pertenecen a otra persona.'
                ], 409);
            }

            $user = User::where('idpersona', $persona->id)->first();

            $emailDuplicadoUser = User::where('email', $email)
                ->when($user, function ($q) use ($user) {
                    return $q->where('id', '<>', $user->id);
                })
                ->first();

            if ($emailDuplicadoUser) {
                return response()->json([
                    'error' => 'El email ya está registrado en un usuario del sistema.'
                ], 409);
            }

            $persona->fechaNac = $request->input('fechaNac');
            $persona->idtipoIdentificacion = $request->input('idtipoIdentificacion');
            $persona->identificacion = $identificacion;
            $persona->nombre1 = $request->input('nombre1');
            $persona->nombre2 = $request->input('nombre2');
            $persona->apellido1 = $request->input('apellido1');
            $persona->apellido2 = $request->input('apellido2');
            $persona->idCiudadNac = $request->input('idciudadNac');
            $persona->celular = $request->input('celular');
            $persona->email = $email;
            $persona->direccion = $request->input('direccion');
            $persona->idCiudadUbicacion = $request->input('idciudadUbicacion');
            $persona->telefonoFijo = $request->input('telefonoFijo');
            $persona->sexo = $request->input('sexo');
            $persona->rh = $request->input('rh');

            if ($request->hasFile('rutaFotoFile')) {
                $persona->rutaFoto = $this->storeLogoPersona($request);
            }

            $persona->save();


            if ($user) {
                $user->email = $email;
                $user->save();
            }

            DB::commit();
            return response()->json($persona, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['error' => $th->getMessage()], 501);
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
}
