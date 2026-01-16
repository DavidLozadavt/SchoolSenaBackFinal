<?php

namespace App\Http\Controllers\gestion_pago;

use App\Http\Controllers\Controller;
use App\Mail\MailService;
use App\Models\ActivationCompanyUser;
use App\Models\AgregarPagoCuenta;
use App\Models\AporteSocio;
use App\Models\AsignacionPagoAdicional;
use App\Models\AsignacionProcesoPago;
use App\Models\AsignacionProcesoTipoDocumento;
use App\Models\ConfiguracionPago;
use App\Models\Contract;
use App\Models\ContratoTransaccion;
use App\Models\DocumentoContrato;
use App\Models\DocumentoEstado;
use App\Models\DocumentoPago;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Person;
use App\Models\Proceso;
use App\Models\Rol;
use App\Models\Status;
use App\Models\Tercero;
use App\Models\TipoDocumento;
use App\Models\Transaccion;
use App\Models\User;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class PagoController extends Controller
{


    /**
     * Obtiene los pagos pendientes para revisar en los próximos días.
     *
     * Esta función consulta la base de datos para recuperar los pagos pendientes
     * cuyo estado se encuentra en [4, 7, 11] y cuyas fechas de pago están dentro
     * del rango de los próximos 5 días a 10 días. Los resultados se devuelven
     * junto con la información de transacción, contratos y persona asociados, así
     * como el estado del pago.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPagosPendientes()
    {
        $fechaActual = now();


        $pagosEstado4y11 = Pago::whereIn('idEstado', [4, 11])
            ->whereDate('fechaPago', '<=', $fechaActual)
            ->with('transaccion.contratos.persona', 'estado')
            ->get();


        $pagosEstado7 = Pago::where('idEstado', 7)
            ->where(function ($query) use ($fechaActual) {
                $query->whereDate('fechaPago', '>=', $fechaActual->subDays(5))
                    ->whereDate('fechaPago', '<=', $fechaActual->addDays(10));
            })
            ->with('transaccion.contratos.persona', 'estado')
            ->get();


        $pagosPendientes = $pagosEstado4y11->merge($pagosEstado7);


        $pagosPendientes = $pagosPendientes->sortByDesc('fechaPago')->values();

        if ($pagosPendientes->isEmpty()) {
            return response()->json(['error' => 'No hay pagos pendientes por revisar en los próximos días. Le recordamos revisar constantemente sus compromisos financieros.'], 404);
        }

        return response()->json($pagosPendientes);
    }



    /**
     * Obtiene todos los pagos disponibles.
     *
     * Esta función consulta la base de datos para recuperar todos los pagos
     * disponibles, ordenados por fecha de pago de forma ascendente. Los resultados
     * incluyen información detallada sobre transacciones, contratos, personas y el
     * estado de cada pago.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function getAllPagos()
    {
        $pagos = Pago::with('transaccion.contratos.persona', 'estado')
            ->whereHas('transaccion', function ($query) {
                $query->whereHas('contratos');
            })
            ->whereNotIn('idEstado', [12]) // Excluir los pagos con idEstado igual a 12
            ->orderBy('fechaPago', 'asc')
            ->get();

        return response()->json($pagos);
    }


    public function pagoMensual(Request $request)
    {
        $idPago = $request->input('idPago');
        $idPersona = $request->input('idPersona');
        $subject = "Pago Mensual";
        // $otrosIdPagos = $request->input('otroIdPago');
        $persona = Person::find($idPersona);


        if (!$persona) {
            throw new \Exception("No se encontró la persona", 505);
        }

        $usuario = User::where('idpersona', $persona->id)->first();

        if (!$usuario) {
            throw new \Exception("No se encontró el usuario asociado a la persona", 505);
        }

        $idUsuario = $usuario->id;

        $correoPersona = $persona->email;

        $informacion = $request->input('informacion');


        $pagoMensual = Pago::findOrFail($idPago);
        $pagoMensual->rutaComprobante = $this->storeComprobante($request);
        $pagoMensual->idEstado = Status::ID_APROBADO;
        $pagoMensual->idMedioPago = 4;
        $pagoMensual->fechaReg = now();
        $pagoMensual->observacion = $informacion;
        $pagoMensual->save();



        // if (!empty($otrosIdPagos)) {
        //     foreach ($otrosIdPagos as $idPagoOtro) {
        //         $pagoOtro = Pago::find($idPagoOtro);

        //         if ($pagoOtro) {
        //             $pagoOtro->idEstado = Status::ID_APROBADO;
        //             $pagoOtro->fechaReg = now();
        //             $pagoOtro->save();
        //         }
        //     }
        // }


        $notification = new Notificacion();
        $notification->estado_id = Status::ID_ACTIVE;
        $mesActual = Carbon::now()->locale('es')->format('F');
        $notification->asunto = 'Se ha realizado el pago para el mes de : ' . $mesActual;
        $notification->mensaje =  $informacion;
        // $notification->route =  '#/gestion-laboral';
        $notification->idUsuarioReceptor = $idUsuario;
        $idUsuarioRemitente = auth()->user()->id;
        $notification->idUsuarioRemitente = $idUsuarioRemitente;
        $notification->idEmpresa = KeyUtil::idCompany();
        $notification->idTipoNotificacion = 1;
        $notification->fecha = Carbon::now()->toDateTimeString();
        $notification->hora = Carbon::now()->format('H:i:s');
        $notification->save();

        $pagoMensual = Pago::findOrFail($idPago);
        $rutaComprobante = $pagoMensual->rutaComprobante;


        $rutaComprobante = str_replace('/storage/comprobante/', '', $rutaComprobante);
        $mailService = new MailService($subject, $informacion);
        $mailService->attach(storage_path("app/public/comprobante/{$rutaComprobante}"), [
            'as' => 'ComprobantePago.png',
        ]);

        Mail::to($correoPersona)->send($mailService);

        $estadoDocumento = new DocumentoEstado();
        $estadoDocumento->fecha = Carbon::now()->toDateTimeString();
        $estadoDocumento->idEstado = Status::ID_ACTIVE;
        $estadoDocumento->observacion = $informacion;
        $estadoDocumento->idPago = $idPago;

        $estadoDocumento->save();

        $identificacion = $persona->identificacion;

        $tercero = Tercero::where('identificacion', $identificacion)->first();

        $idTercero = $tercero->id;

        $agregarPagoCuenta = new AgregarPagoCuenta();
        $agregarPagoCuenta->idPago = $idPago;
        $agregarPagoCuenta->idSubcuentaPropia = 2;
        $agregarPagoCuenta->idTercero =  $idTercero;
        $agregarPagoCuenta->naturaleza = AgregarPagoCuenta::CREDITO;
        $agregarPagoCuenta->save();

        $agregarPagoCuenta = new AgregarPagoCuenta();
        $agregarPagoCuenta->idPago = $idPago;
        $agregarPagoCuenta->idSubcuentaPropia = 1;
        $agregarPagoCuenta->idTercero =  $idTercero;
        $agregarPagoCuenta->naturaleza = AgregarPagoCuenta::DEBITO;
        $agregarPagoCuenta->save();


        return response()->json($pagoMensual, 201);
    }





    private function storeComprobante(Request $request, $default = true)
    {
        $rutaComprobante = null;

        if ($default) {
            $rutaComprobante = Pago::RUTA_COMPROBANTE_DEFAULT;
        }
        if ($request->hasFile('rutaComprobanteFile')) {
            $rutaComprobante =
                '/storage/' .
                $request
                ->file('rutaComprobanteFile')
                ->store(Pago::RUTA_COMPROBANTE, ['disk' => 'public']);
        }
        return $rutaComprobante;
    }




    public function getPagoByIdentificacion(Request $request)
    {
        $query = $request->input('query');

        if (!$query || !preg_match('/^[0-9]+$/', $query)) {
            return response()->json(['error' => 'Debes ingresar una identificación válida'], 400);
        }

        $persona = Person::with('contrato.transacciones.pago.estado')->where('identificacion', $query)->first();

        if (!$persona) {
            return response()->json(['error' => 'No se encontró ninguna persona con esa identificación'], 404);
        }

        $contratos = Contract::where('idpersona', $persona->id)->get();

        $contratos->load('transacciones.pago.estado');

        $pago = $contratos->pluck('transacciones')->flatten()->pluck('pago')->collapse()
            ->filter(function ($pago) {
                $estadosPermitidos = [4, 7, 11];
                $fechaPago = Carbon::parse($pago->fechaPago);
                $fechaActual = now();

                return in_array($pago->estado->id, $estadosPermitidos) &&
                    $fechaPago->between($fechaActual->copy()->subDays(6), $fechaActual->copy()->addDays(10));
            });

        if ($pago->isEmpty()) {
            return response()->json(['error' => 'No se encontraron procesos abiertos para tu pago en este momento. Por favor, asegúrate de revisar constantemente el estado de tu transacción. Recuerda que si te aparece un proceso de pago es esencial completar y enviar los documentos requeridos para asegurar la efectividad de tu proceso de pago. Gracias por tu colaboración.'], 404);
        }

        return response()->json(['persona' => $persona, 'pago' => $pago->values()]);
    }




    public function getPagosByIdentificacion(Request $request)
    {
        $query = $request->input('query');

        if (!$query || !preg_match('/^[0-9]+$/', $query)) {
            return response()->json(['error' => 'Debes ingresar una identificación válida'], 400);
        }

        // Buscar la persona por identificación
        $persona = Person::with('contrato.transacciones.pago.estado')->where('identificacion', $query)->first();

        if (!$persona) {
            return response()->json(['error' => 'No se encontró ninguna persona con esa identificación'], 404);
        }

        // Obtener los contratos de la persona por el campo idpersona
        $contratos = Contract::where('idpersona', $persona->id)->get();

        // Puedes cargar las relaciones de los contratos si es necesario
        $contratos->load('transacciones.pago.estado');

        // Obtener todos los pagos de los contratos y colapsar la lista
        $pagos = $contratos->pluck('transacciones')->flatten()->pluck('pago')->collapse();

        return response()->json(['persona' => $persona, 'contratos' => $contratos, 'pagos' => $pagos]);
    }



    public function documentosPago($id)
    {
        $documentos = TipoDocumento::where('idProceso', $id)->get();
        return response()->json($documentos);
    }



    /**
     * Carga los documentos asociados a un pago en el sistema.
     *
     * Esta función crea y guarda un nuevo documento de pago en la base de datos, junto con la ruta del archivo adjunto.
     * Además, actualiza el estado del pago asociado y envía una notificación al usuario correspondiente.
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que contiene los datos del documento de pago a cargar.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON que contiene el documento de pago recién creado.
     */
    public function cargarDocumentosPago(Request $request)
    {
        $documentoPago = new DocumentoPago();
        $documentoPago->idPago = $request->input('idPago');
        $documentoPago->idEstado = Status::ID_PENDIENTE;
        $documentoPago->idAsignacionTipoDocumentoProceso = $request->input('idAsignacionTipoDocumentoProceso');
        $documentoPago->ruta = $this->storeDocumentoPagos($request);
        $documentoPago->fechaCarga = \Carbon\Carbon::now()->toDateTimeString();
        $documentoPago->save();

        $pago = Pago::findOrFail($documentoPago->idPago);
        $pago->idEstado =  Status::ID_EN_ESPERA;
        $pago->save();

        $idUsuarioRemitente = auth()->user()->id;
        $usuarioRemitente = Person::where('id', $idUsuarioRemitente)->first();
        $nombreApellido = $usuarioRemitente->nombre1 . ' ' . $usuarioRemitente->apellido1;

        $notification = new Notificacion();
        $notification->estado_id = Status::ID_ACTIVE;

        $mesActual = Carbon::now()->locale('es')->format('F');
        $notification->asunto = 'Carga de archivos para el mes de ' . $mesActual;

        $notification->mensaje = "$nombreApellido ha cargado los documentos para empezar el proceso de pago del mes de $mesActual";

        $notification->route = '#/pagos-contratos';
        $notification->idUsuarioReceptor = Session::get('company_id'); // Revisar esto: debe ser el id del usuario de la empresa, no de la company
        $notification->idUsuarioRemitente = $idUsuarioRemitente;
        $notification->idEmpresa = Session::get('company_id');
        $notification->idTipoNotificacion = 1;
        $notification->fecha = \Carbon\Carbon::now()->toDateTimeString();
        $notification->hora = \Carbon\Carbon::now()->format('H:i:s');

        $notification->save();

        return response()->json($documentoPago, 201);
    }



    /**
     * Almacena el documento de pagos en el sistema de archivos.
     *
     * Esta función toma una solicitud HTTP como entrada y, opcionalmente, puede almacenar un archivo adjunto en el sistema de archivos.
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que puede contener un archivo adjunto.
     * @param bool $default Un indicador booleano que indica si se debe utilizar una ruta predeterminada para el almacenamiento.
     * @return string|null La ruta del documento de pagos almacenado, o NULL si no se proporciona un archivo o si la operación de almacenamiento falla.
     */
    private function storeDocumentoPagos(Request $request, $default = true)
    {
        $rutaDocumentoPago = null;

        // Si se activa el modo predeterminado, se utiliza la ruta de documento de pagos predeterminada
        if ($default) {
            $rutaDocumentoPago = DocumentoPago::RUTA_DOCUMENTO_PAGOS_DEFAULT;
        }

        // Si la solicitud contiene un archivo adjunto, se almacena en el sistema de archivos y se obtiene su ruta
        if ($request->hasFile('rutaFile')) {
            $rutaDocumentoPago = '/storage/' . $request
                ->file('rutaFile')
                ->store(DocumentoPago::RUTA_DOCUMENTO_PAGOS, ['disk' => 'public']);
        }

        // Retorna la ruta del documento de pagos almacenado
        return $rutaDocumentoPago;
    }



    /**
     * Obtiene los documentos asociados a un proceso de pago mensual.
     *
     * Esta función busca en la base de datos los documentos asociados al proceso de pago mensual.
     * Retorna una lista de los tipos de documentos asignados a dicho proceso.
     *
     * @return \Illuminate\Http\JsonResponse La respuesta JSON que contiene la lista de documentos asociados al proceso de pago mensual.
     */
    public function getDocumentosPago()
    {
        // Definir el nombre del proceso de pago mensual
        $nombreProceso = 'PAGO MENSUAL';

        $proceso = Proceso::where('nombreProceso', $nombreProceso)->first();

        if (!$proceso) {
            return response()->json(['error' => 'Proceso no encontrado'], 404);
        }

        $idProceso = $proceso->id;

        $tipoDocumentos = AsignacionProcesoTipoDocumento::with('proceso', 'tipoDocumento')
            ->where('idProceso', $idProceso)
            ->get();

        return response()->json($tipoDocumentos);
    }



    /**
     * Obtiene los documentos asociados a un pago para su revisión.
     *
     * Esta función recibe un ID de pago como parámetro de solicitud y busca los documentos asociados a ese pago.
     * Retorna una lista de los documentos relacionados con el pago para su revisión.
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que contiene el ID de pago.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON que contiene la lista de documentos asociados al pago para su revisión.
     */
    public function getDocumentosForRevision(Request $request)
    {
        $idPago = $request->input('idPago');

        $documentoPago = DocumentoPago::with('AsignacionTipoDocumentoProceso.tipoDocumento', 'estado')
            ->where('idPago', $idPago)
            ->get();

        if ($documentoPago->isEmpty()) {
            return response()->json(['error' => 'A este proceso de pago aún no se le han cargado documentos'], 404);
        }

        return response()->json($documentoPago);
    }



    /**
     * Actualiza el estado de un documento de pago.
     *
     * Esta función recibe el ID de un documento de pago y actualiza su estado a "Aprobado".
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que contiene el ID del documento de pago.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON que contiene el documento de pago actualizado con el estado "Aprobado".
     */
    public function updateEstadoDocumentoPago(Request $request)
    {

        $idDocumento = $request->input('idDocumento');

        $documentoEstado = DocumentoPago::findOrFail($idDocumento);
        $documentoEstado->idEstado = Status::ID_APROBADO;
        $documentoEstado->save();

        return response()->json($documentoEstado, 201);
    }



    /**
     * Rechaza un documento de pago y notifica a la persona correspondiente.
     *
     * Esta función recibe el ID del documento a rechazar, así como otros datos necesarios
     * como la observación del rechazo, el ID de la persona asociada y el ID del pago.
     * Actualiza el estado del documento y del pago a "Reprobado", envía un correo electrónico
     * de notificación a la persona y crea un registro de notificación en la base de datos.
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que contiene los datos necesarios para rechazar el documento.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON que contiene el documento de pago actualizado con el estado "Reprobado".
     * @throws \Exception Si no se encuentra la persona asociada al documento.
     */
    public function rechazarDocumentoEstado(Request $request)
    {
        // $idDocumento = $request->input('idDocumento');
        $observacion = $request->input('observacion');
        $idPersona = $request->input('idPersona');
        $idPago = $request->input('idPago');
        $subject = "Rechazo de documento";

        $persona = Person::find($idPersona);

        if (!$persona) {
            throw new \Exception("No se encontró la persona", 505);
        }

        $idUser = $persona->usuario->id;
        $correoPersona = $persona->email;

        // $documentoEstado = DocumentoPago::findOrFail($idDocumento);
        // $documentoEstado->idEstado = Status::ID_REPROBADO;
        // $documentoEstado->save();

        $pago = Pago::findOrFail($idPago);
        $pago->idEstado =  Status::ID_REPROBADO;
        $pago->save();


        $notification = new Notificacion();
        $notification->estado_id = Status::ID_ACTIVE;
        $notification->asunto = 'Rechazo de documento';
        $notification->mensaje = $observacion;
        $notification->route =  '#/gestion-laboral';
        $notification->idUsuarioRemitente = auth()->user()->id;
        $notification->idUsuarioReceptor = $idUser;
        $notification->idEmpresa = KeyUtil::idCompany();
        $notification->idTipoNotificacion = 2;
        $notification->fecha = Carbon::now()->toDateTimeString();
        $notification->hora = Carbon::now()->format('H:i:s');

        // Enviar correo electrónico de notificación
        $mailService = new MailService($subject, $observacion);
        Mail::to($correoPersona)->send($mailService);

        $notification->save();

        // Crear un nuevo registro de estado del documento
        $estadoDocumento = new DocumentoEstado();
        $estadoDocumento->fecha = Carbon::now()->toDateTimeString();
        $estadoDocumento->idEstado = Status::ID_ACTIVE;
        $estadoDocumento->observacion = $observacion;
        // $estadoDocumento->idDocumento = $idDocumento;

        $estadoDocumento->save();

        // Retornar la respuesta JSON con el documento de pago actualizado
        return response()->json($estadoDocumento, 201);
    }



    public function getCertificacionBancaria($id)
    {


        $contract = Contract::with([
            'documentosContrato.AsignacionTipoDocumentoProceso.tipoDocumento'
        ])->find($id);

        if (!$contract || $contract->documentosContrato->isEmpty()) {
            $contrato = Contract::where('id', $id)->first();
            $idContrato2 = $contrato ? $contrato->idContrato : null;

            if ($idContrato2) {
                $contract = Contract::with([
                    'documentosContrato.AsignacionTipoDocumentoProceso.tipoDocumento'
                ])->find($idContrato2);
            }
        }

        if (!$contract || $contract->documentosContrato->isEmpty()) {
            return response()->json(['error' => 'Contrato no encontrado'], 404);
        }

        $documentosTipoDocumentoId = $contract->documentosContrato->filter(function ($documento) {
            return $documento->AsignacionTipoDocumentoProceso->tipoDocumento->id == 9;
        });

        return response()->json($documentosTipoDocumentoId->values());
    }


    /**
     * Obtiene los documentos reprobados asociados a un pago.
     *
     * Esta función recibe el ID de un pago como parámetro de solicitud y busca los documentos asociados a ese pago
     * que hayan sido marcados como reprobados. Retorna una lista de los documentos reprobados
     * junto con sus estados y observaciones asociadas.
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que contiene el ID del pago.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON que contiene la lista de documentos reprobados asociados al pago.
     */
    public function getDocumentosReprobados(Request $request)
    {
        $idPago = $request->input('idPago');

        // Buscar los documentos de pago reprobados asociados al pago
        $documentoEstadoReprobado = DocumentoPago::with('estado', 'AsignacionTipoDocumentoProceso.tipoDocumento', 'documentosEstado')
            ->where('idPago', $idPago)
            ->where('idEstado', 7)
            ->get();

        // Verificar si no se encontraron documentos reprobados
        if ($documentoEstadoReprobado->isEmpty()) {
            return response()->json(['error' => 'No se encontraron observaciones para este documento'], 404);
        }

        // Retornar la respuesta JSON con los documentos reprobados
        return response()->json($documentoEstadoReprobado);
    }




    /**
     * Actualiza un documento de pago que ha sido marcado como reprobado.
     *
     * Esta función recibe el ID de un documento de pago que ha sido marcado como reprobado.
     * Actualiza la ruta del documento, la fecha de carga, el estado del documento y el estado del pago asociado.
     * Luego, devuelve el documento de pago actualizado.
     *
     * @param \Illuminate\Http\Request $request La solicitud HTTP que contiene los datos del documento de pago.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON que contiene el documento de pago actualizado.
     */
    public function updateDocumentoPagoReprobado(Request $request)
    {

        $idDocumentoPago = $request->input('idDocumento');
        $documento = DocumentoPago::findOrFail($idDocumentoPago);
        $documento->ruta = $this->storeDocumentoPagos($request); // Almacenar la ruta del documento
        $documento->fechaCarga = Carbon::now()->toDateTimeString(); // Establecer la fecha de carga
        $documento->idEstado = Status::ID_PENDIENTE; // Establecer el estado del documento como pendiente
        $documento->save();

        // Obtener el ID del pago asociado al documento
        $idPago = $documento->idPago;

        // Buscar y actualizar el estado del pago asociado
        $pago = Pago::findOrFail($idPago);
        $pago->idEstado = Status::ID_EN_ESPERA; // Establecer el estado del pago como en espera
        $pago->save();

        // Retornar la respuesta JSON con el documento de pago actualizado
        return response()->json($documento, 201);
    }


    public function storePagoAdicional(Request $request)
    {
        $idContrato = $request->input('idContrato');
        $observacion = $request->input('observacion');

        $transaccion = new Transaccion();
        $transaccion->fechaTransaccion = Carbon::now()->toDateString();
        $transaccion->hora = Carbon::now()->format('H:i');
        $transaccion->valor = $request->input('valor');
        $transaccion->idEstado = Status::ID_PENDIENTE;
        $transaccion->save();

        $asignacionContratoTransaccion = new ContratoTransaccion();
        $asignacionContratoTransaccion->contrato_id = $idContrato;
        $asignacionContratoTransaccion->transaccion_id = $transaccion->id;
        $asignacionContratoTransaccion->save();

        $pago = new Pago();
        $pago->valor = $transaccion->valor;
        $pago->idTransaccion = $transaccion->id;
        $pago->fechaPago = Carbon::now()->toDateString();
        $pago->idEstado = Status::ID_PENDIENTE_ADICIONAL;
        $pago->observacion = $observacion;


        $pago->save();

        return response()->json($pago, 201);
    }



    public function getPagosAdicionales($id, $idPago)
    {
        $datosContratoTransaccion = ContratoTransaccion::with([
            'transaccion.pago' => function ($query) {
                $query->whereIn('idEstado', [Status::ID_PENDIENTE_ADICIONAL, Status::ID_ACTIVE])
                    ->with('estado');
            },
        ])
            ->where('contrato_id', $id)
            ->whereHas('transaccion.pago', function ($query) {
                $query->whereIn('idEstado', [Status::ID_PENDIENTE_ADICIONAL, Status::ID_ACTIVE]);
            })
            ->orderBy('id', 'desc')
            ->get();

        if ($datosContratoTransaccion->isEmpty()) {
            return response()->json(['error' => 'No se encontraron pagos'], 204);
        }

        $asignacionPago = AsignacionPagoAdicional::where('idPago', $idPago)->get();

        foreach ($datosContratoTransaccion as $transaccion) {
            if ($transaccion->transaccion && $transaccion->transaccion->pago) {
                foreach ($transaccion->transaccion->pago as $pago) {

                    $pagoRelacionado = $asignacionPago->firstWhere('idPagoAdicional', $pago->id);
                    $pago->hasAdditionalPayment = $pagoRelacionado ? true : false;
                }
            }
        }

        return response()->json($datosContratoTransaccion);
    }






    public function updatePagoAdicional(Request $request, $id)
    {

        //poner funcion no se actualice si ya esta en la tabla asignacion
        $observacion = $request->input('observacion');
        $valor = $request->input('valor');

        $pago = Pago::findOrFail($id);

        $pago->valor = $valor;
        $pago->observacion = $observacion;

        $pago->save();

        $transaccion = $pago->transaccion;

        if ($transaccion) {
            $transaccion->valor = $valor;
            $transaccion->save();
        }

        return response()->json($pago, 200);
    }


    public function destroy(int $id)
    {     //poner funcion no se elimine si ya esta en la tabla asignacion
        try {
            $pagoAdicional = Pago::findOrFail($id);

            if ($pagoAdicional && $pagoAdicional->idEstado == 12) {
                $pagoAdicional->delete();

                ContratoTransaccion::where('transaccion_id', $pagoAdicional->idTransaccion)->delete();
                Transaccion::where('id', $pagoAdicional->idTransaccion)->delete();

                return response()->json([], 204);
            } else {
                return response()->json(['error' => 'El pago no existe o no tiene el idEstado adecuado para ser eliminado.'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el pago.'], 500);
        }
    }


    public function updateValorAdicional(Request $request)
    {
        $idPago1 = $request->input('idPago1');
        $idPago2 = $request->input('idPago2');
        $checkboxMarcado = $request->input('checkboxMarcado', false);

        $pago1 = Pago::find($idPago1);
        $pago2 = Pago::find($idPago2);

        if (!$pago1 || !$pago2) {
            return response()->json(['mensaje' => 'Pago no encontrado'], 404);
        }

        if ($checkboxMarcado) {

            $pago1->excedente += $pago2->valor;
            $pago1->save();


            DB::table('asignacionPagosAdicionales')->updateOrInsert(
                ['idPago' => $idPago1, 'idPagoAdicional' => $idPago2],

            );
        } else {


            $pago1->excedente -= $pago2->valor;
            $pago1->save();


            DB::table('asignacionPagosAdicionales')
                ->where('idPago', $idPago1)
                ->where('idPagoAdicional', $idPago2)
                ->delete();
        }




        $pago2->save();

        return response()->json(['mensaje' => 'Valores actualizados correctamente']);
    }




    public function getPagosAdicionalesActivos(Request $request)
    {
        $idContrato = $request->input('idContrato');
        $idPago = $request->input('idPago');

        $datosContratoTransaccion = ContratoTransaccion::with([
            'transaccion.pago' => function ($query) {
                $query->whereIn('idEstado', [Status::ID_ACTIVE])
                    ->with('estado');
            },
        ])
            ->where('contrato_id', $idContrato)
            ->whereHas('transaccion.pago', function ($query) {
                $query->whereIn('idEstado', [Status::ID_ACTIVE]);
            })
            ->orderBy('id', 'desc')
            ->get();

        if ($datosContratoTransaccion->isEmpty()) {
            return response()->json(['error' => 'No se encontraron pagos'], 204);
        }

        return response()->json($datosContratoTransaccion);
    }




    public function getComprobante(Request $request)
    {
        try {
            $identificacion = $request->input('identificacion');
            $fecha = $request->input('fecha');

            $persona = Person::where('identificacion', $identificacion)->first();

            if ($persona) {
                $personaId = $persona->id;

                $company = ActivationCompanyUser::with('company')->where('user_id', $personaId)->first();

                $contratos = $persona->contrato;

                $pagosTotales = collect();

                foreach ($contratos as $contrato) {
                    $transacciones = $contrato->transacciones;

                    if ($transacciones->isNotEmpty()) {
                        $idsTransacciones = $transacciones->pluck('id')->toArray();

                        $pagos = Pago::whereIn('idTransaccion', $idsTransacciones)
                            ->whereDate('fechaReg', '=', $fecha)
                            ->get();

                        $pagosTotales = $pagosTotales->merge($pagos);
                    }
                }

                if ($pagosTotales->isNotEmpty()) {
                    return response()->json([
                        'company' => $company,
                        'pagos' => $pagosTotales,
                    ]);
                } else {
                    return response()->json(['error' => 'No se encontraron pagos asociados a los contratos de esta persona.'], 404);
                }
            } else {
                return response()->json(['error' => 'No se encontró una persona con la identificación proporcionada.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en el servidor.'], 500);
        }
    }



    public function getPagoById(Request $request)
    {
        $idPago = $request->input('idPago');

        $pago = Pago::with('estado')->find($idPago);

        if ($pago) {

            return response()->json($pago);
        } else {
            return response()->json(['error' => 'Pago no encontrado'], 404);
        }
    }



    public function getConfiguracionesPago()
    {
        $documents = AsignacionProcesoPago::with('configuracionPago', 'proceso')
            ->whereHas('configuracionPago', function ($query) {
                $query->where('estado', 'ACTIVO');
            })
            ->get();

        return response()->json($documents);
    }



    public function storeConfiguracionPago(Request $request)
    {


        $configuracionPago = new ConfiguracionPago();
        $configuracionPago->titulo = $request->input('titulo');
        $configuracionPago->detalle = $request->input('detalle');
        $configuracionPago->estado = $request->input('estado');
        $configuracionPago->valor = $request->input('valor');
        $configuracionPago->idCompany = KeyUtil::idCompany();

        $configuracionPago->save();

        $asignacion = new AsignacionProcesoPago();
        $asignacion->idConfiguracionPago = $configuracionPago->id;
        $asignacion->idProceso = $request->input('idProceso');

        $asignacion->save();

        return response()->json($asignacion, 201);
    }


    public function updateConfiguracionPago(Request $request, int $id)
    {
        $asignacion = AsignacionProcesoPago::where('id', $id)->firstOrFail();

        $configuracionPago = ConfiguracionPago::findOrFail($asignacion->idConfiguracionPago);

        $configuracionPago->titulo = $request->input('titulo', $configuracionPago->titulo);
        $configuracionPago->detalle = $request->input('detalle', $configuracionPago->detalle);
        $configuracionPago->estado = $request->input('estado', $configuracionPago->estado);
        $configuracionPago->valor = $request->input('valor', $configuracionPago->valor);

        $configuracionPago->save();

        $asignacion->idProceso = $request->input('idProceso', $asignacion->idProceso);
        $asignacion->save();

        return response()->json([
            'configuracionPago' => $configuracionPago,
            'asignacion' => $asignacion
        ], 200);
    }


    public function destroyConfiguracionPago(int $id)
    {
        $asignacion = AsignacionProcesoPago::findOrFail($id);

        $configuracionPago = ConfiguracionPago::findOrFail($asignacion->idConfiguracionPago);

        $asignacion->delete();
        $configuracionPago->delete();

        return response()->json([], 204);
    }
}
