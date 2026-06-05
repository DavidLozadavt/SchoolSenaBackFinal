<?php

namespace App\Http\Controllers\gestion_pago;

use App\Http\Controllers\Controller;
use App\Mail\MailService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ActivationCompanyUser;
use App\Models\AgregarPagoCuenta;
use App\Models\AporteSocio;
use App\Models\AsignacionFacturaTransaccion;
use App\Models\AsignacionPagoAdicional;
use App\Models\AsignacionProcesoPago;
use App\Models\AsignacionProcesoTipoDocumento;
use App\Models\ConfiguracionPago;
use App\Models\ConfiguracionPagoVigencia;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Contract;
use App\Models\ContratoTransaccion;
use App\Models\DocumentoContrato;
use App\Models\DocumentoEstado;
use App\Models\DocumentoPago;
use App\Models\Notificacion;
use App\Models\Matricula;
use App\Models\Pago;
use App\Models\Person;
use App\Models\Proceso;
use App\Models\Rol;
use App\Models\Status;
use App\Models\Tercero;
use App\Models\TipoDocumento;
use App\Models\TipoFactura;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use App\Models\User;
use App\Util\KeyUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $hoy = Carbon::today()->toDateString();
        $pagos = ConfiguracionPago::with([
            'asignacionProcesoPago.proceso',
            'configuracionPagoVigencias' => function ($query) use ($hoy) {
                $query->whereDate('fechaInicial', '<=', $hoy)
                    ->whereDate('fechaFinal', '>=', $hoy)
                    ->orderBy('fechaInicial', 'desc');
            }
        ])
            ->get();

        $pagos->each(function ($config) {
            $config->setAttribute('configuracionPagoVigenciaActual', $config->configuracionPagoVigencias->first());
            unset($config->configuracionPagoVigencias);
        });

        return response()->json($pagos);
    }



    public function storeConfiguracionPago(Request $request)
    {
        $vigenciasInput = $request->input('configuracionPagoVigencia');
        if (!is_array($vigenciasInput) || empty($vigenciasInput)) {
            $vigenciasInput = [[
                'fechaInicial' => $request->input('vigenciaFechaInicial'),
                'fechaFinal' => $request->input('vigenciaFechaFinal'),
                'valor' => $request->input('valor'),
            ]];
        }

        $configuracionPago = new ConfiguracionPago();
        $configuracionPago->titulo = $request->input('titulo');
        $configuracionPago->detalle = $request->input('detalle');
        $configuracionPago->estado = $request->input('estado');
        $configuracionPago->valor = $request->input('valor');
        $configuracionPago->porcentajeIva = $request->input('porcentajeIva');
        $configuracionPago->frecuenciaPago = $request->input('frecuenciaPago');
        $configuracionPago->idCompany = KeyUtil::idCompany();
        $configuracionPago->idContabilizacion = $request->input('idContabilizacion');
        $configuracionPago->obligatorio = $request->input('obligatorio', false);
        $configuracionPago->porcentaje = $request->input('porcentaje');
        $configuracionPago->topeValor = $request->input('topeValor');
        $configuracionPago->frecuenciaTope = $request->input('frecuenciaTope');
        $configuracionPago->tipoMovimiento = $request->input('tipoMovimiento');
        $configuracionPago->idCentroCosto = $request->input('idCentroCosto');
        $configuracionPago->idClaseVehiculo = $request->input('idClaseVehiculo');
        $configuracionPago->save();

        foreach ($vigenciasInput as $vigencia) {
            $fechaInicial = $vigencia['fechaInicial'] ?? null;
            $fechaFinal = $vigencia['fechaFinal'] ?? null;
            $tieneFechaInicial = !empty($fechaInicial);
            $tieneFechaFinal = !empty($fechaFinal);

            if ($tieneFechaInicial xor $tieneFechaFinal) {
                $configuracionPago->delete();
                return response()->json([
                    'error' => 'Para registrar vigencia debe enviar fechaInicial y fechaFinal.'
                ], 422);
            }

            if (!$tieneFechaInicial && !$tieneFechaFinal) {
                continue;
            }

            if ($this->existeCruceVigenciaConfiguracionPago(
                (int) $configuracionPago->id,
                (string) $fechaInicial,
                (string) $fechaFinal
            )) {
                $configuracionPago->delete();
                return response()->json([
                    'error' => 'Ya existe una vigencia con rango de fechas que se cruza para esta configuración de pago.'
                ], 422);
            }

            $confiVigenciaValor = new ConfiguracionPagoVigencia();
            $confiVigenciaValor->idConfiguracionPago = $configuracionPago->id;
            $confiVigenciaValor->fechaInicial = $fechaInicial;
            $confiVigenciaValor->fechaFinal = $fechaFinal;
            $confiVigenciaValor->valor = $vigencia['valor'] ?? $configuracionPago->valor;
            $confiVigenciaValor->save();
        }

        $asignacion = new AsignacionProcesoPago();
        $asignacion->idConfiguracionPago = $configuracionPago->id;
        $asignacion->idProceso = $request->input('idProceso');

        $asignacion->save();





        return response()->json($asignacion, 201);
    }


    public function updateConfiguracionPago(Request $request, int $id)
    {
        $vigenciasInput = $request->input('configuracionPagoVigencia');
        if (!is_array($vigenciasInput) || empty($vigenciasInput)) {
            $vigenciasInput = [[
                'fechaInicial' => $request->input('vigenciaFechaInicial'),
                'fechaFinal' => $request->input('vigenciaFechaFinal'),
                'valor' => $request->input('valor'),
            ]];
        }

        $configuracionPago = ConfiguracionPago::findOrFail($id);


        $asignacion = AsignacionProcesoPago::where('idConfiguracionPago', $configuracionPago->id)->first();


        $configuracionPago->titulo = $request->input('titulo', $configuracionPago->titulo);
        $configuracionPago->detalle = $request->input('detalle', $configuracionPago->detalle);
        $configuracionPago->estado = $request->input('estado', $configuracionPago->estado);
        $configuracionPago->valor = $request->input('valor', $configuracionPago->valor);
        $configuracionPago->porcentajeIva = $request->input('porcentajeIva');
        $configuracionPago->frecuenciaPago = $request->input('frecuenciaPago');
        $configuracionPago->idContabilizacion = $request->input('idContabilizacion');
        $configuracionPago->obligatorio = $request->input('obligatorio', false);
        $configuracionPago->porcentaje = $request->input('porcentaje');
        $configuracionPago->topeValor = $request->input('topeValor');
        $configuracionPago->idClaseVehiculo = $request->input('idClaseVehiculo');
        $configuracionPago->frecuenciaTope = $request->input('frecuenciaTope');
        $configuracionPago->tipoMovimiento = $request->input('tipoMovimiento');
        $configuracionPago->idCentroCosto = $request->input('idCentroCosto');
        $configuracionPago->save();

        if (!$asignacion) {
            if (!$request->filled('idProceso')) {
                return response()->json([
                    'error' => 'No existe asignación para esta configuración. Debe enviar idProceso para crearla.'
                ], 422);
            }

            $asignacion = new AsignacionProcesoPago();
            $asignacion->idConfiguracionPago = $configuracionPago->id;
        }

        $asignacion->idProceso = $request->input('idProceso', $asignacion->idProceso);
        $asignacion->save();

        foreach ($vigenciasInput as $vigencia) {
            $idVigencia = isset($vigencia['id']) ? (int) $vigencia['id'] : null;
            $fechaInicial = $vigencia['fechaInicial'] ?? null;
            $fechaFinal = $vigencia['fechaFinal'] ?? null;
            $tieneFechaInicial = !empty($fechaInicial);
            $tieneFechaFinal = !empty($fechaFinal);

            if ($tieneFechaInicial xor $tieneFechaFinal) {
                return response()->json([
                    'error' => 'Para registrar vigencia debe enviar fechaInicial y fechaFinal.'
                ], 422);
            }

            if (!$tieneFechaInicial && !$tieneFechaFinal) {
                continue;
            }

            if ($this->existeCruceVigenciaConfiguracionPago(
                (int) $configuracionPago->id,
                (string) $fechaInicial,
                (string) $fechaFinal,
                $idVigencia
            )) {
                return response()->json([
                    'error' => 'Ya existe una vigencia con rango de fechas que se cruza para esta configuraci�n de pago.'
                ], 422);
            }

            if (!empty($idVigencia)) {
                $confiVigenciaValor = ConfiguracionPagoVigencia::where('idConfiguracionPago', $configuracionPago->id)
                    ->where('id', $idVigencia)
                    ->first();

                if (!$confiVigenciaValor) {
                    return response()->json([
                        'error' => 'La vigencia indicada no existe para esta configuraci�n de pago.'
                    ], 404);
                }
            } else {
                $confiVigenciaValor = new ConfiguracionPagoVigencia();
                $confiVigenciaValor->idConfiguracionPago = $configuracionPago->id;
            }

            $confiVigenciaValor->fechaInicial = $fechaInicial;
            $confiVigenciaValor->fechaFinal = $fechaFinal;
            $confiVigenciaValor->valor = $vigencia['valor'] ?? $configuracionPago->valor;
            $confiVigenciaValor->save();
        }




        return response()->json([
            'configuracionPago' => $configuracionPago,
            'asignacion' => $asignacion
        ], 200);
    }


    public function destroyConfiguracionPago(int $id)
    {
        $configuracionPago = ConfiguracionPago::findOrFail($id);


        AsignacionProcesoPago::where('idConfiguracionPago', $configuracionPago->id)->delete();
        $configuracionPago->delete();

        return response()->json([], 204);
    }


    private function existeCruceVigenciaConfiguracionPago(
        int $idConfiguracionPago,
        string $fechaInicial,
        string $fechaFinal,
        ?int $ignorarIdVigencia = null
    ): bool {
        $query = ConfiguracionPagoVigencia::where('idConfiguracionPago', $idConfiguracionPago)
            ->whereDate('fechaInicial', '<=', $fechaFinal)
            ->whereDate('fechaFinal', '>=', $fechaInicial);

        if (!empty($ignorarIdVigencia)) {
            $query->where('id', '!=', $ignorarIdVigencia);
        }

        return $query->exists();
    }


    /**
     * Genera factura de venta y líneas desde configuraciones económicas activas de un proceso.
     * No modifica configuracionPago ni vigencias.
     */
    public function generarFacturaValoresEconomicos(Request $request)
    {
        $idProceso = $request->input('idProceso');
        if (empty($idProceso)) {
            return response()->json(['error' => 'idProceso es obligatorio.'], 422);
        }

        $proceso = Proceso::find($idProceso);
        if (!$proceso) {
            return response()->json(['error' => 'Proceso no encontrado.'], 404);
        }

        $idCompany = (int) ($request->input('idCompany') ?: KeyUtil::idCompany());
        $idTercero = $request->input('idTercero') ?: $request->input('idEstudiante');

        $asignaciones = AsignacionProcesoPago::with('configuracionPago')
            ->where('idProceso', $idProceso)
            ->whereHas('configuracionPago', function ($query) use ($idCompany) {
                $query->where('idCompany', $idCompany)
                    ->where('estado', 'ACTIVO');
            })
            ->get();

        $conceptosInput = $request->input('conceptos', []);
        $idsSolicitados = collect($conceptosInput)
            ->pluck('idConfiguracionPago')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($idsSolicitados->isNotEmpty()) {
            $idsDelProceso = $asignaciones->pluck('idConfiguracionPago')->map(fn ($id) => (int) $id);
            $invalidos = $idsSolicitados->diff($idsDelProceso);
            if ($invalidos->isNotEmpty()) {
                return response()->json([
                    'error' => 'Uno o más conceptos no pertenecen al proceso seleccionado o no están activos.',
                    'idsInvalidos' => $invalidos->values(),
                ], 422);
            }
            $asignaciones = $asignaciones->filter(
                fn ($a) => $idsSolicitados->contains((int) $a->idConfiguracionPago)
            )->values();
        }

        if ($asignaciones->isEmpty()) {
            return response()->json([
                'error' => 'No hay conceptos económicos activos asociados al proceso.',
            ], 422);
        }

        $lineas = [];
        $totalSinIva = 0.0;
        $totalIva = 0.0;
        $idsUsados = [];

        foreach ($asignaciones as $asignacion) {
            $config = $asignacion->configuracionPago;
            if (!$config || strtoupper((string) $config->estado) !== 'ACTIVO') {
                continue;
            }

            $idConfig = (int) $config->id;
            if (in_array($idConfig, $idsUsados, true)) {
                continue;
            }
            $idsUsados[] = $idConfig;

            $valorLinea = $this->resolverValorLineaConfiguracion($config, $conceptosInput, $idConfig);
            if ($valorLinea < 0) {
                return response()->json(['error' => 'No se permiten valores negativos en los conceptos.'], 422);
            }

            $porcentajeIva = (float) ($config->porcentajeIva ?? 0);
            $ivaLinea = $porcentajeIva > 0 ? round($valorLinea * ($porcentajeIva / 100), 2) : 0.0;

            $lineas[] = [
                'config' => $config,
                'valor' => $valorLinea,
                'iva' => $ivaLinea,
            ];
            $totalSinIva += $valorLinea;
            $totalIva += $ivaLinea;
        }

        if (empty($lineas)) {
            return response()->json(['error' => 'No se encontraron conceptos válidos para generar la factura.'], 422);
        }

        $totalSinIva = round($totalSinIva, 2);
        $totalIva = round($totalIva, 2);
        $valorMasIva = round($totalSinIva + $totalIva, 2);

        $tieneColumnaIdConfig = Schema::hasColumn('detalleFactura', 'idConfiguracionPago');

        try {
            DB::beginTransaction();

            $factura = new Factura();
            $lastFactura = Factura::where('idTipoFactura', TipoFactura::VENTA)
                ->orderBy('id', 'desc')
                ->first();
            $factura->numeroFactura = $lastFactura
                ? str_pad((int) $lastFactura->numeroFactura + 1, 5, '0', STR_PAD_LEFT)
                : '00001';
            $factura->fecha = Carbon::now();
            $factura->valor = $totalSinIva;
            $factura->valorIva = $totalIva;
            $factura->valorMasIva = $valorMasIva;
            if (!empty($idTercero)) {
                $factura->idTercero = $idTercero;
            }
            $factura->idCompany = $idCompany;
            $factura->idTipoFactura = TipoFactura::VENTA;
            if (auth()->check()) {
                $factura->idUser = auth()->id();
            }
            $factura->save();

            $detallesCreados = [];
            foreach ($lineas as $linea) {
                $config = $linea['config'];
                $detalleFactura = new DetalleFactura();
                $detalleFactura->idFactura = $factura->id;
                $detalleFactura->detalle = $config->titulo ?? $config->detalle ?? 'Concepto académico';
                $detalleFactura->valor = $linea['valor'] + $linea['iva'];
                if ($tieneColumnaIdConfig) {
                    $detalleFactura->idConfiguracionPago = $config->id;
                }
                $detalleFactura->save();
                $detallesCreados[] = $detalleFactura;
            }

            $transaccion = new Transaccion();
            $transaccion->valor = $valorMasIva;
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->fechaTransaccion = Carbon::now();
            $transaccion->tipoCartera = 'CXC';
            $transaccion->idTipoTransaccion = TipoTransaccion::VENTA;
            $transaccion->idEstado = Status::ID_PENDIENTE;
            $transaccion->excedente = $valorMasIva;
            $transaccion->save();

            $asignacionFacturaTransaccion = new AsignacionFacturaTransaccion();
            $asignacionFacturaTransaccion->idFactura = $factura->id;
            $asignacionFacturaTransaccion->idTransaccion = $transaccion->id;
            $asignacionFacturaTransaccion->save();

            $pago = new Pago();
            $pago->fechaPago = Carbon::now();
            $pago->fechaReg = Carbon::now();
            $pago->valor = 0;
            $pago->excedente = $valorMasIva;
            $pago->idEstado = Status::ID_PENDIENTE;
            $pago->idTransaccion = $transaccion->id;
            $pago->save();

            DB::commit();

            $factura->load(['detalles', 'tercero', 'transacciones.pago.estado']);

            return response()->json([
                'message' => 'Factura académica generada correctamente.',
                'factura' => $this->formatearFacturaAcademica($factura, $proceso),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'No fue posible generar la factura académica.',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }


    public function getFacturasAcademicas(Request $request)
    {
        $idCompany = (int) ($request->input('idCompany') ?: KeyUtil::idCompany());
        $tieneColumnaIdConfig = Schema::hasColumn('detalleFactura', 'idConfiguracionPago');

        $query = Factura::with(['detalles', 'tercero', 'transacciones.pago.estado'])
            ->where('idTipoFactura', TipoFactura::VENTA)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->orderBy('id', 'desc');

        if ($tieneColumnaIdConfig) {
            $query->whereHas('detalles', function ($q) {
                $q->whereNotNull('idConfiguracionPago');
            });
        } else {
            $idsConfig = ConfiguracionPago::where('idCompany', $idCompany)->pluck('id');
            if ($idsConfig->isEmpty()) {
                return response()->json([]);
            }
            $titulos = ConfiguracionPago::whereIn('id', $idsConfig)->pluck('titulo')->filter();
            $query->whereHas('detalles', function ($q) use ($titulos) {
                $q->where(function ($inner) use ($titulos) {
                    foreach ($titulos as $titulo) {
                        $inner->orWhere('detalle', $titulo);
                    }
                });
            });
        }

        $facturas = $query->get();

        $resultado = $facturas->map(function (Factura $factura) {
            $proceso = $this->inferirProcesoFacturaAcademica($factura);
            return $this->formatearFacturaAcademica($factura, $proceso);
        });

        return response()->json($resultado->values());
    }


    public function getFacturaAcademica(int $id)
    {
        $idCompany = (int) KeyUtil::idCompany();
        $factura = Factura::with(['detalles', 'tercero', 'transacciones.pago.estado'])
            ->where('id', $id)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Factura no encontrada.'], 404);
        }

        $proceso = $this->inferirProcesoFacturaAcademica($factura);

        return response()->json($this->formatearFacturaAcademica($factura, $proceso));
    }


    /**
     * Registra el pago de una factura académica contra su transacción y el registro en pagos.
     * Usado en validación de solicitudes de inscripción / matrícula.
     */
    public function registrarPagoFacturaAcademica(Request $request, int $id)
    {
        $request->validate([
            'idMedioPago' => ['required', 'integer'],
            'idTipoPago' => ['nullable', 'integer'],
            'valorAbono' => ['nullable', 'numeric', 'min:0.01'],
            'contexto' => ['nullable', 'string', 'max:120'],
        ]);

        $idCompany = (int) KeyUtil::idCompany();
        $factura = Factura::with(['detalles', 'tercero', 'transacciones.pago.estado'])
            ->where('id', $id)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Factura no encontrada.'], 404);
        }

        $transaccion = $factura->transacciones->first();
        if (!$transaccion) {
            return response()->json(['error' => 'La factura no tiene transacción asociada.'], 422);
        }

        $pago = $transaccion->pago->first();
        if (!$pago) {
            return response()->json(['error' => 'La transacción no tiene registro de pago.'], 422);
        }

        if ((int) $pago->idEstado === Status::ID_APROBADO && (float) $pago->excedente <= 0) {
            $proceso = $this->inferirProcesoFacturaAcademica($factura);

            return response()->json([
                'message' => 'La factura ya está pagada.',
                'idTransaccion' => $transaccion->id,
                'factura' => $this->formatearFacturaAcademica($factura, $proceso),
            ]);
        }

        $valorAbono = $request->has('valorAbono')
            ? (float) $request->input('valorAbono')
            : (float) $pago->excedente;

        if ($valorAbono <= 0 || (float) $pago->excedente <= 0) {
            return response()->json(['error' => 'No hay saldo pendiente por registrar.'], 422);
        }

        try {
            DB::beginTransaction();

            $restarExcedente = min((float) $pago->excedente, $valorAbono);
            $pago->excedente = round((float) $pago->excedente - $restarExcedente, 2);
            $pago->valor = round((float) $pago->valor + $restarExcedente, 2);
            $pago->fechaPago = Carbon::now()->format('Y-m-d');
            $pago->fechaReg = Carbon::now()->format('Y-m-d');
            $pago->idMedioPago = (int) $request->input('idMedioPago');
            $pago->numeroFact = $factura->numeroFactura;

            if ((float) $pago->excedente <= 0) {
                $pago->idEstado = Status::ID_APROBADO;
            }

            $pago->save();

            if ($request->filled('idTipoPago')) {
                $transaccion->idTipoPago = (int) $request->input('idTipoPago');
            }

            if (isset($transaccion->excedente) && (float) $transaccion->excedente > 0) {
                $transaccion->excedente = max(
                    0,
                    round((float) $transaccion->excedente - $restarExcedente, 2)
                );
            }

            if ((float) $pago->excedente <= 0) {
                $transaccion->idEstado = Status::ID_APROBADO;
            }

            $transaccion->save();

            DB::commit();

            $factura->refresh();
            $factura->load(['detalles', 'tercero', 'transacciones.pago.estado']);
            $proceso = $this->inferirProcesoFacturaAcademica($factura);

            return response()->json([
                'message' => 'Pago registrado correctamente.',
                'idTransaccion' => $transaccion->id,
                'factura' => $this->formatearFacturaAcademica($factura, $proceso),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'No fue posible registrar el pago.',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }


    private function resolverValorLineaConfiguracion(
        ConfiguracionPago $config,
        array $conceptosInput,
        int $idConfig
    ): float {
        foreach ($conceptosInput as $item) {
            if ((int) ($item['idConfiguracionPago'] ?? 0) === $idConfig && isset($item['valor'])) {
                $valorManual = (float) $item['valor'];
                return max(0, $valorManual);
            }
        }

        $hoy = Carbon::today()->toDateString();
        $vigencia = ConfiguracionPagoVigencia::where('idConfiguracionPago', $config->id)
            ->whereDate('fechaInicial', '<=', $hoy)
            ->whereDate('fechaFinal', '>=', $hoy)
            ->orderBy('fechaInicial', 'desc')
            ->first();

        if ($vigencia && $vigencia->valor !== null) {
            return max(0, (float) $vigencia->valor);
        }

        return max(0, (float) ($config->valor ?? 0));
    }


    private function inferirProcesoFacturaAcademica(Factura $factura): ?Proceso
    {
        $tieneColumnaIdConfig = Schema::hasColumn('detalleFactura', 'idConfiguracionPago');

        foreach ($factura->detalles as $detalle) {
            $idConfig = $tieneColumnaIdConfig ? ($detalle->idConfiguracionPago ?? null) : null;

            if (!$idConfig) {
                $config = ConfiguracionPago::where('titulo', $detalle->detalle)
                    ->orWhere('detalle', $detalle->detalle)
                    ->first();
                $idConfig = $config?->id;
            }

            if (!$idConfig) {
                continue;
            }

            $asignacion = AsignacionProcesoPago::with('proceso')
                ->where('idConfiguracionPago', $idConfig)
                ->first();

            if ($asignacion?->proceso) {
                return $asignacion->proceso;
            }
        }

        return null;
    }


    private function formatearFacturaAcademica(Factura $factura, $proceso): array
    {
        $estado = 'PENDIENTE';
        $transaccion = $factura->transacciones->first();
        $pago = $transaccion?->pago?->first();

        if ($pago) {
            if ((int) $pago->idEstado === Status::ID_APROBADO && (float) $pago->excedente <= 0) {
                $estado = 'PAGADO';
            } elseif ((int) $pago->idEstado === Status::ID_PENDIENTE) {
                $estado = 'PENDIENTE';
            } elseif ($pago->estado) {
                $estado = $pago->estado->estado ?? $pago->estado->nombre ?? 'PENDIENTE';
            }
        }

        $tieneColumnaIdConfig = Schema::hasColumn('detalleFactura', 'idConfiguracionPago');

        $detalles = $factura->detalles->map(function (DetalleFactura $detalle) use ($tieneColumnaIdConfig) {
            $idConfig = $tieneColumnaIdConfig ? ($detalle->idConfiguracionPago ?? null) : null;
            $config = $idConfig ? ConfiguracionPago::find($idConfig) : null;

            return [
                'id' => $detalle->id,
                'idFactura' => $detalle->idFactura,
                'idConfiguracionPago' => $idConfig,
                'concepto' => $config?->titulo ?? $detalle->detalle,
                'detalle' => $detalle->detalle,
                'valor' => (float) $detalle->valor,
            ];
        })->values();

        $procesoPayload = null;
        if ($proceso instanceof Proceso) {
            $procesoPayload = [
                'id' => $proceso->id,
                'nombreProceso' => $proceso->nombreProceso,
            ];
        }

        $valorFacturaOriginal = (float) ($factura->valorMasIva ?? $factura->valor);
        
        // Si el valor viene en 0, calcularlo sumando los detalles (cantidad * valor)
        if ($valorFacturaOriginal <= 0) {
            $valorCalculado = $detalles->sum(function ($detalle) {
                return (float) ($detalle['valor'] ?? 0) * (float) ($detalle['cantidad'] ?? 1);
            });
            $valorFinal = $valorCalculado;
        } else {
            $valorFinal = $valorFacturaOriginal;
        }

        return [
            'id' => $factura->id,
            'numeroFactura' => $factura->numeroFactura,
            'fecha' => $factura->fecha,
            'valor' => $valorFinal,
            'valorSinIva' => $valorFinal,
            'valorIva' => (float) ($factura->valorIva ?? 0),
            'estado' => $estado,
            'saldoPendiente' => $pago ? max(0, (float) $pago->excedente) : $valorFinal,
            'idTransaccion' => $transaccion?->id,
            'idTercero' => $factura->idTercero,
            'tercero' => $factura->tercero,
            'proceso' => $procesoPayload,
            'detalles' => $detalles,
        ];
    }


    /**
     * Listado de solicitudes de inscripción/matricula derivadas de facturas académicas pendientes de validar.
     * idSolicitud = id de la factura académica.
     */
    public function getSolicitudesInscripcion(Request $request)
    {
        $idCompany = (int) ($request->input('idCompany') ?: KeyUtil::idCompany());
        $filtroEstadoFactura = strtoupper((string) $request->input('estadoFactura', 'TODOS'));
        $filtroEstadoSolicitud = strtoupper((string) $request->input('estadoSolicitud', 'PENDIENTES'));

        $facturas = $this->queryFacturasAcademicas($idCompany)->get();

        $items = $facturas
            ->map(function (Factura $factura) use ($idCompany) {
                $proceso = $this->inferirProcesoFacturaAcademica($factura);
                $facturaPayload = $this->formatearFacturaAcademica($factura, $proceso);

                return $this->formatearSolicitudInscripcion($facturaPayload, $factura, $proceso, $idCompany);
            })
            ->filter(function (array $item) use ($filtroEstadoFactura, $filtroEstadoSolicitud) {
                if ($filtroEstadoFactura !== 'TODOS') {
                    $estadoFactura = strtoupper((string) ($item['estadoFactura'] ?? ''));
                    if ($estadoFactura !== $filtroEstadoFactura) {
                        return false;
                    }
                }

                $estadoSolicitud = strtoupper((string) ($item['estado'] ?? ''));

                if ($filtroEstadoSolicitud === 'PENDIENTES') {
                    return ($item['estado'] ?? '') === 'PENDIENTE';
                }

                if ($filtroEstadoSolicitud === 'APROBADAS') {
                    return ($item['estado'] ?? '') === 'APROBADA';
                }

                if ($filtroEstadoSolicitud === 'RECHAZADAS') {
                    return $estadoSolicitud === 'RECHAZADA';
                }

                return true;
            })
            ->values();

        return response()->json($items);
    }


    /**
     * Marca la validación administrativa de inscripción como aprobada (paso final del wizard).
     * Requiere factura pagada. Persiste la marca en pagos.observacion.
     */
    public function aprobarValidacionSolicitudInscripcion(Request $request, int $idFactura)
    {
        $request->validate([
            'observaciones' => ['nullable', 'string', 'max:480'],
        ]);

        $idCompany = (int) KeyUtil::idCompany();
        $factura = $this->queryFacturasAcademicas($idCompany)
            ->where('id', $idFactura)
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Solicitud no encontrada.'], 404);
        }

        $proceso = $this->inferirProcesoFacturaAcademica($factura);
        $facturaPayload = $this->formatearFacturaAcademica($factura, $proceso);
        $estadoFactura = strtoupper((string) ($facturaPayload['estado'] ?? 'PENDIENTE'));

        if (!in_array($estadoFactura, ['PAGADO', 'PAGADA'], true)) {
            return response()->json([
                'error' => 'La factura debe estar pagada antes de aprobar la validación.',
            ], 422);
        }

        if ($this->solicitudValidacionAprobada($factura)) {
            $solicitud = $this->formatearSolicitudInscripcion($facturaPayload, $factura, $proceso, $idCompany);

            return response()->json([
                'message' => 'La solicitud ya estaba aprobada.',
                'solicitud' => $solicitud,
            ]);
        }

        $pago = $this->resolverPagoFacturaAcademica($factura);
        if (!$pago) {
            return response()->json(['error' => 'No se encontró el registro de pago de la factura.'], 422);
        }

        $this->aplicarMarcaValidacionInscripcionEnPago(
            $pago,
            $request->input('observaciones')
        );
        $pago->save();

        $factura->refresh();
        $factura->load(['detalles', 'tercero', 'transacciones.pago.estado']);
        $facturaPayload = $this->formatearFacturaAcademica($factura, $proceso);
        $solicitud = $this->formatearSolicitudInscripcion($facturaPayload, $factura, $proceso, $idCompany);

        return response()->json([
            'message' => 'Validación de inscripción aprobada.',
            'solicitud' => $solicitud,
        ]);
    }


    /**
     * Genera un token HMAC firmado para acceso público al portal del aspirante.
     * No requiere base de datos — el token codifica idFactura e idCompany.
     */
    private function generarTokenPortalAspirante(int $idFactura, int $idCompany): string
    {
        $payload = "{$idCompany}:{$idFactura}";
        $secret  = config('app.key');
        $hmac    = hash_hmac('sha256', $payload, $secret);
        return rtrim(strtr(base64_encode("{$payload}:{$hmac}"), '+/', '-_'), '=');
    }

    /**
     * Verifica un token de portal y retorna [idCompany, idFactura] o null si inválido.
     */
    private function verificarTokenPortalAspirante(string $token): ?array
    {
        $padded  = str_pad(strtr($token, '-_', '+/'), strlen($token) % 4 === 0 ? strlen($token) : strlen($token) + (4 - strlen($token) % 4), '=');
        $decoded = base64_decode($padded, true);
        if (!$decoded) {
            return null;
        }
        $parts = explode(':', $decoded, 3);
        if (count($parts) !== 3) {
            return null;
        }
        [$idCompany, $idFactura, $hmacRecibido] = $parts;
        $payload      = "{$idCompany}:{$idFactura}";
        $secret       = config('app.key');
        $hmacEsperado = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($hmacEsperado, $hmacRecibido)) {
            return null;
        }
        return ['idCompany' => (int) $idCompany, 'idFactura' => (int) $idFactura];
    }

    /**
     * Endpoint público (sin auth) para el portal del aspirante.
     * Verifica el token HMAC y retorna datos de la factura e inscripción.
     */
    public function getPortalAspirante(string $token)
    {
        $datos = $this->verificarTokenPortalAspirante($token);
        if (!$datos) {
            return response()->json(['error' => 'Enlace de acceso inválido o expirado.'], 403);
        }

        $idCompany = $datos['idCompany'];
        $idFactura = $datos['idFactura'];

        $factura = Factura::with(['detalles', 'tercero', 'transacciones.pago.estado'])
            ->where('id', $idFactura)
            ->where('idCompany', $idCompany)
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Solicitud no encontrada.'], 404);
        }

        $proceso        = $this->inferirProcesoFacturaAcademica($factura);
        $facturaPayload = $this->formatearFacturaAcademica($factura, $proceso);
        $solicitud      = $this->formatearSolicitudInscripcion($facturaPayload, $factura, $proceso, $idCompany);
        $estudiante     = $this->formatearEstudianteSolicitud($factura, $idCompany);
        $company        = \App\Models\Company::find($idCompany);

        // Resolver campos específicos solicitados
        $transaccion = $factura->transacciones->first();
        $pago = $transaccion?->pago?->first();

        return response()->json([
            'solicitud'          => $solicitud,
            'factura'            => $facturaPayload,
            'estudiante'         => $estudiante,
            'nombreInstitucion'  => $company?->razonSocial ?? 'La institución',
            // Agregando la estructura JSON requerida explícitamente en el root de la respuesta
            'detalleFactura'     => $facturaPayload['detalles'] ?? [],
            'transaccion'        => $transaccion,
            'pagos'              => $pago,
            'saldoPendiente'     => (float) ($facturaPayload['saldoPendiente'] ?? 0),
            'valorTotal'         => (float) ($facturaPayload['valor'] ?? 0),
            'numeroFactura'      => $factura->numeroFactura,
            'pdfUrl'             => url("api/portal-aspirante/{$token}/factura-pdf"),
        ]);
    }

    /**
     * Sube un comprobante de pago en pdf/imagen y lo asocia al pago de la transacción de la factura.
     */
    public function subirComprobantePortalAspirante(Request $request, string $token)
    {
        $datos = $this->verificarTokenPortalAspirante($token);
        if (!$datos) {
            return response()->json(['error' => 'Enlace de acceso inválido o expirado.'], 403);
        }

        $request->validate([
            'comprobante' => ['required', 'file', 'mimes:pdf,jpeg,png,jpg', 'max:5120'],
        ]);

        $idCompany = $datos['idCompany'];
        $idFactura = $datos['idFactura'];

        $factura = Factura::with(['transacciones.pago'])
            ->where('id', $idFactura)
            ->where('idCompany', $idCompany)
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Factura no encontrada.'], 404);
        }

        $transaccion = $factura->transacciones->first();
        $pago = $transaccion?->pago?->first();

        if (!$pago) {
            return response()->json(['error' => 'No se encontró un registro de pago asociado a la factura.'], 400);
        }

        if ($request->hasFile('comprobante')) {
            $file = $request->file('comprobante');
            $extension = $file->getClientOriginalExtension();
            $filename = 'comprobante_' . $pago->id . '_' . time() . '.' . $extension;
            $path = $file->storeAs(Pago::PATH, $filename, 'public');

            // Guardar documento comprobante de pago
            $documentoPago = new DocumentoPago();
            $documentoPago->idPago = $pago->id;
            $documentoPago->idEstado = Status::ID_PENDIENTE; // Pendiente de revisión
            $documentoPago->ruta = 'storage/' . $path;
            $documentoPago->fechaCarga = \Carbon\Carbon::now()->toDateTimeString();
            $documentoPago->save();

            // Cambiar estado del pago a PAGO_EN_REVISION o equivalente (ID_EN_ESPERA es usado en bandeja administrativa de revisión)
            $pago->idEstado = Status::ID_EN_ESPERA; 
            $pago->save();

            return response()->json([
                'message' => 'Comprobante cargado con éxito.',
                'documento' => $documentoPago
            ]);
        }

        return response()->json(['error' => 'No se recibió ningún archivo.'], 400);
    }

    /**
     * Genera y devuelve el PDF de la factura académica para el portal del aspirante.
     * Endpoint público: GET /api/portal-aspirante/{token}/factura-pdf
     */
    public function generarFacturaPdfPortalAspirante(string $token)
    {
        $datos = $this->verificarTokenPortalAspirante($token);
        if (!$datos) {
            return response()->json(['error' => 'Enlace de acceso inválido o expirado.'], 403);
        }

        $idCompany = $datos['idCompany'];
        $idFactura = $datos['idFactura'];

        $factura = Factura::with(['detalles', 'tercero', 'transacciones.pago.estado'])
            ->where('id', $idFactura)
            ->where('idCompany', $idCompany)
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Factura no encontrada.'], 404);
        }

        $proceso        = $this->inferirProcesoFacturaAcademica($factura);
        $facturaPayload = $this->formatearFacturaAcademica($factura, $proceso);
        $estudiante     = $this->formatearEstudianteSolicitud($factura, $idCompany);
        $company        = \App\Models\Company::find($idCompany);
        $nombreInstitucion = $company?->razonSocial ?? 'La Institución';

        $procesoPayload = null;
        if ($proceso instanceof \App\Models\Proceso) {
            $procesoPayload = ['nombreProceso' => $proceso->nombreProceso];
        }

        $pdf = Pdf::loadView('pdf.factura-academica', [
            'factura'           => $facturaPayload,
            'estudiante'        => $estudiante,
            'proceso'           => $procesoPayload,
            'nombreInstitucion' => $nombreInstitucion,
        ])->setPaper('a4', 'portrait');

        $filename = 'factura-academica-' . ($factura->numeroFactura ?? $idFactura) . '.pdf';

        return $pdf->stream($filename);
    }


    /**
     * Envía correo enriquecido de confirmación de recepción al aspirante.
     * Se llama desde el Paso 1 del wizard de validación al hacer clic en "Siguiente".
     * Usa la plantilla email-inscripcion.blade.php con tabla de datos y botón CTA.
     * No modifica estados ni genera facturas.
     */
    public function notificarRecepcionSolicitudInscripcion(Request $request, int $idFactura)
    {
        $idCompany = (int) KeyUtil::idCompany();
        $factura = $this->queryFacturasAcademicas($idCompany)
            ->where('id', $idFactura)
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Solicitud no encontrada.'], 404);
        }

        // ── Resolver datos del aspirante ──────────────────────────────────
        $emailAspirante  = $factura->tercero?->email ?? null;
        $nombreAspirante = $factura->tercero?->nombre ?? 'Aspirante';
        $documento       = $factura->tercero?->identificacion ?? '';

        $matricula = $this->resolverMatriculaEstudiante($factura->tercero, $idCompany);
        if ($matricula?->person) {
            $emailAspirante  = $emailAspirante  ?? ($matricula->person->email ?? null);
            $nombreAspirante = $this->formatearNombrePersona($matricula->person) ?? $nombreAspirante;
            $documento       = $documento !== '' ? $documento : ($matricula->person->identificacion ?? '');
        }

        if (!$emailAspirante) {
            return response()->json([
                'message'        => 'No se encontró correo del aspirante. Se omitió el envío.',
                'correo_enviado' => false,
            ]);
        }

        // ── Datos del proceso y factura ───────────────────────────────────
        $proceso        = $this->inferirProcesoFacturaAcademica($factura);
        $nombrePrograma = $proceso instanceof \App\Models\Proceso
            ? ($proceso->nombreProceso ?? 'Proceso académico')
            : 'Proceso académico';

        $facturaPayload  = $this->formatearFacturaAcademica($factura, $proceso);
        $valorTotal      = (float) ($facturaPayload['valor'] ?? 0);
        $saldoPendiente  = (float) ($facturaPayload['saldoPendiente'] ?? $valorTotal);
        $numeroFactura   = $factura->numeroFactura ?? "FAC-{$idFactura}";
        $estadoFactura   = strtoupper((string) ($facturaPayload['estado'] ?? 'PENDIENTE'));

        $estadoTexto = match (true) {
            in_array($estadoFactura, ['PAGADA', 'PAGADO'], true) => 'PAGADA – Pendiente de validación final',
            $estadoFactura === 'ANULADA'                          => 'ANULADA',
            default                                               => 'PENDIENTE DE PAGO',
        };

        $fechaLimite = $saldoPendiente > 0
            ? \Carbon\Carbon::now()->addDays(15)->format('d/m/Y')
            : '';

        $company           = \App\Models\Company::find($idCompany);
        $nombreInstitucion = $company?->razonSocial ?? 'La institución';

        // ── Generar URL del portal público ────────────────────────────────
        $token       = $this->generarTokenPortalAspirante($idFactura, $idCompany);
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://sena-school.virtualt.org'), '/');
        $portalUrl   = "{$frontendUrl}/portal-aspirante/{$token}";

        // ── Enviar correo con la plantilla HTML enriquecida ───────────────
        $subject = "Solicitud de inscripción recibida – {$nombrePrograma}";

        try {
            Mail::send('email-inscripcion', [
                'nombreAspirante'   => $nombreAspirante,
                'documento'         => $documento,
                'programa'          => $nombrePrograma,
                'estadoInscripcion' => $estadoTexto,
                'valorTotal'        => $saldoPendiente > 0 ? $saldoPendiente : $valorTotal,
                'numeroFactura'     => $numeroFactura,
                'fechaLimite'       => $fechaLimite,
                'portalUrl'         => $portalUrl,
                'nombreInstitucion' => $nombreInstitucion,
            ], function ($mail) use ($emailAspirante, $subject, $nombreInstitucion) {
                $mail->to($emailAspirante)
                     ->subject($subject)
                     ->from(config('mail.from.address'), $nombreInstitucion);
            });
        } catch (\Exception $e) {
            \Log::error('Error enviando correo inscripción: ' . $e->getMessage());
            return response()->json([
                'message'        => 'No se pudo enviar el correo al aspirante.',
                'correo_enviado' => false,
                'error'          => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message'        => 'Correo de recepción enviado al aspirante.',
            'correo_enviado' => true,
            'email'          => $emailAspirante,
            'portal_url'     => $portalUrl,
        ]);
    }


    /**
     * Detalle de una solicitud (por id de factura académica) para el wizard de validación.
     */
    public function getSolicitudInscripcion(int $idFactura)
    {
        $idCompany = (int) KeyUtil::idCompany();
        $factura = $this->queryFacturasAcademicas($idCompany)
            ->where('id', $idFactura)
            ->first();

        if (!$factura) {
            return response()->json(['error' => 'Solicitud no encontrada.'], 404);
        }

        $proceso = $this->inferirProcesoFacturaAcademica($factura);
        $facturaPayload = $this->formatearFacturaAcademica($factura, $proceso);
        $solicitud = $this->formatearSolicitudInscripcion($facturaPayload, $factura, $proceso, $idCompany);
        $estudiante = $this->formatearEstudianteSolicitud($factura, $idCompany);

        $documento = $factura->tercero?->identificacion ?? '';
        $respuestasFormulario = $this->resolverRespuestasFormulario($documento, $idCompany, $factura);

        $transaccion = $factura->transacciones->first();
        $pago = $transaccion?->pago?->first();
        $documentosPago = $pago ? \App\Models\DocumentoPago::with('estado')->where('idPago', $pago->id)->get() : [];

        return response()->json([
            'solicitud' => $solicitud,
            'factura' => $facturaPayload,
            'estudiante' => $estudiante,
            'respuestasFormulario' => $respuestasFormulario,
            'documentosPago' => $documentosPago,
        ]);
    }

    private function resolverRespuestasFormulario(string $documento, int $idCompany, ?Factura $factura = null)
    {
        $company = \App\Models\Company::find($idCompany);
        if (!$company || !$company->idFormularioInscripcion) {
            return null;
        }

        $respuestaObj = null;

        // 1. Intentar resolver usando FormResponseID guardado en la matricula
        if ($factura) {
            $matricula = $this->resolverMatriculaEstudiante($factura->tercero, $idCompany);
            if ($matricula && $matricula->observacion && strpos($matricula->observacion, 'FormResponseID:') === 0) {
                $respId = (int) str_replace('FormResponseID:', '', $matricula->observacion);
                if ($respId > 0) {
                    $respuestaObj = \App\Models\FormularioRespuesta::where('idFormulario', $company->idFormularioInscripcion)
                        ->where('id', $respId)
                        ->first();
                }
            }
        }

        // 2. Fallback: Buscar por documento (si no es '0' y no está vacío)
        if (!$respuestaObj && !empty($documento) && $documento !== '0') {
            $respuestas = \App\Models\FormularioRespuesta::where('idFormulario', $company->idFormularioInscripcion)->get();
            foreach ($respuestas as $resp) {
                $listaResp = $resp->respuestas;
                if (is_array($listaResp)) {
                    foreach ($listaResp as $r) {
                        if (isset($r['valor']) && (string)$r['valor'] === (string)$documento) {
                            $respuestaObj = $resp;
                            break 2;
                        }
                    }
                }
            }
        }

        // 3. Fallback: Para registros de prueba existentes, buscar por coincidencia de fecha/hora de creación (margen de 5 segundos)
        if (!$respuestaObj && $factura) {
            $createdAt = $factura->created_at;
            if ($createdAt) {
                $respuestaObj = \App\Models\FormularioRespuesta::where('idFormulario', $company->idFormularioInscripcion)
                    ->whereBetween('created_at', [
                        $createdAt->copy()->subSeconds(5),
                        $createdAt->copy()->addSeconds(5)
                    ])
                    ->first();
            }
        }

        // 4. Formatear la respuesta si se encontró
        if ($respuestaObj) {
            $listaResp = $respuestaObj->respuestas;
            $result = [];
            if (is_array($listaResp)) {
                foreach ($listaResp as $rInner) {
                    $preg = \App\Models\FormularioPregunta::find($rInner['idPregunta'] ?? 0);
                    $result[] = [
                        'pregunta' => $preg ? $preg->titulo : 'Pregunta sin título',
                        'respuesta' => $rInner['valor'] ?? ''
                    ];
                }
            }
            return [
                'formulario' => \App\Models\Formulario::where('id', $company->idFormularioInscripcion)->value('titulo'),
                'respuestas' => $result
            ];
        }

        return null;
    }


    private function queryFacturasAcademicas(int $idCompany)
    {
        $tieneColumnaIdConfig = Schema::hasColumn('detalleFactura', 'idConfiguracionPago');

        $query = Factura::with(['detalles', 'tercero', 'transacciones.pago.estado'])
            ->where('idTipoFactura', TipoFactura::VENTA)
            ->where(function ($q) use ($idCompany) {
                $q->where('idCompany', $idCompany)->orWhereNull('idCompany');
            })
            ->orderBy('id', 'desc');

        if ($tieneColumnaIdConfig) {
            $query->whereHas('detalles', function ($q) {
                $q->whereNotNull('idConfiguracionPago');
            });
        } else {
            $idsConfig = ConfiguracionPago::where('idCompany', $idCompany)->pluck('id');
            if ($idsConfig->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            $titulos = ConfiguracionPago::whereIn('id', $idsConfig)->pluck('titulo')->filter();
            $query->whereHas('detalles', function ($q) use ($titulos) {
                $q->where(function ($inner) use ($titulos) {
                    foreach ($titulos as $titulo) {
                        $inner->orWhere('detalle', $titulo);
                    }
                });
            });
        }

        return $query;
    }


    private function formatearSolicitudInscripcion(
        array $facturaPayload,
        Factura $factura,
        $proceso,
        int $idCompany
    ): array {
        $matricula = $this->resolverMatriculaEstudiante($factura->tercero, $idCompany);
        $persona = $matricula?->person;
        $nombreEstudiante = $this->formatearNombrePersona($persona)
            ?? ($factura->tercero->nombre ?? 'Estudiante');
        $documento = $persona?->identificacion ?? ($factura->tercero->identificacion ?? '');
        $estadoFactura = strtoupper((string) ($facturaPayload['estado'] ?? 'PENDIENTE'));
        $saldoPendiente = (float) ($facturaPayload['saldoPendiente'] ?? 0);
        $validacionAprobada = $this->solicitudValidacionAprobada($factura);
        $estadoSolicitud = $this->mapearEstadoSolicitud(
            $estadoFactura,
            $matricula,
            $validacionAprobada,
            $saldoPendiente
        );

        return [
            'idSolicitud' => (int) $factura->id,
            'idFactura' => (int) $factura->id,
            'numeroSolicitud' => 'SOL-FAC-' . ($factura->numeroFactura ?? $factura->id),
            'numeroFactura' => $factura->numeroFactura,
            'idEstudiante' => $persona?->id ?? ($factura->idTercero ?? null),
            'idTercero' => $factura->idTercero,
            'nombreEstudiante' => $nombreEstudiante,
            'documento' => $documento,
            'email' => $persona?->email ?? ($factura->tercero->email ?? null),
            'telefono' => $persona?->celular ?? ($factura->tercero->telefono ?? null),
            'idMatricula' => $matricula?->id,
            'estadoMatricula' => $matricula?->estado,
            'idPrograma' => $proceso instanceof Proceso ? (int) $proceso->id : null,
            'nombrePrograma' => $proceso instanceof Proceso
                ? ($proceso->nombreProceso ?? 'Proceso académico')
                : 'Proceso académico',
            'codigoPrograma' => $proceso instanceof Proceso ? ('PROC-' . $proceso->id) : '—',
            'idProceso' => $proceso instanceof Proceso ? (int) $proceso->id : null,
            'nombreProceso' => $proceso instanceof Proceso ? $proceso->nombreProceso : null,
            'fechaSolicitud' => $factura->fecha
                ? Carbon::parse($factura->fecha)->toDateString()
                : ($factura->created_at?->toDateString() ?? Carbon::today()->toDateString()),
            'estado' => $estadoSolicitud,
            'validacionCompletada' => $validacionAprobada || $estadoSolicitud === 'APROBADA',
            'estadoFactura' => $estadoFactura,
            'saldoPendiente' => $saldoPendiente,
            'totalFactura' => (float) ($facturaPayload['valor'] ?? 0),
            'idTransaccion' => $facturaPayload['idTransaccion'] ?? null,
            'requierePago' => $saldoPendiente > 0 && $estadoFactura === 'PENDIENTE',
        ];
    }


    private function formatearEstudianteSolicitud(Factura $factura, int $idCompany): ?array
    {
        $matricula = $this->resolverMatriculaEstudiante($factura->tercero, $idCompany);
        $persona = $matricula?->person;

        if (!$persona && $factura->tercero) {
            return [
                'idPersona' => null,
                'idMatricula' => $matricula?->id,
                'nombreCompleto' => $factura->tercero->nombre ?? 'Estudiante',
                'tipoDocumento' => 'CC',
                'documento' => $factura->tercero->identificacion ?? '',
                'email' => $factura->tercero->email,
                'celular' => $factura->tercero->telefono,
                'telefono' => $factura->tercero->telefono,
                'estadoMatricula' => $matricula?->estado,
            ];
        }

        if (!$persona) {
            return null;
        }

        $tipoDoc = $persona->relationLoaded('tipoIdentificacion')
            ? ($persona->tipoIdentificacion?->tipo ?? $persona->tipoIdentificacion?->nombre ?? 'CC')
            : 'CC';

        return [
            'idPersona' => (int) $persona->id,
            'idMatricula' => $matricula?->id,
            'nombreCompleto' => $this->formatearNombrePersona($persona) ?? $persona->nombre1,
            'tipoDocumento' => $tipoDoc,
            'documento' => $persona->identificacion,
            'email' => $persona->email,
            'celular' => $persona->celular,
            'telefono' => $persona->telefonoFijo,
            'fechaNacimiento' => $persona->fechaNac
                ? Carbon::parse($persona->fechaNac)->toDateString()
                : null,
            'direccion' => $persona->direccion,
            'estadoMatricula' => $matricula?->estado,
        ];
    }


    private function resolverMatriculaEstudiante(?Tercero $tercero, int $idCompany): ?Matricula
    {
        if (!$tercero || empty($tercero->identificacion)) {
            return null;
        }

        return Matricula::with(['person.tipoIdentificacion'])
            ->where('idCompany', $idCompany)
            ->whereHas('person', function ($query) use ($tercero) {
                $query->where('identificacion', $tercero->identificacion);
            })
            ->whereIn('estado', [
                'INSCRIPCION',
                'PENDIENTE',
                'EN ESPERA',
                'MATRICULADO',
                'EN FORMACION',
                'CURSANDO',
            ])
            ->orderByDesc('id')
            ->first();
    }


    private function formatearNombrePersona(?Person $persona): ?string
    {
        if (!$persona) {
            return null;
        }

        $partes = array_filter([
            $persona->nombre1,
            $persona->nombre2,
            $persona->apellido1,
            $persona->apellido2,
        ]);

        $nombre = trim(implode(' ', $partes));

        return $nombre !== '' ? $nombre : null;
    }


    private function mapearEstadoSolicitud(
        string $estadoFactura,
        ?Matricula $matricula,
        bool $validacionAprobada = false,
        float $saldoPendiente = 0
    ): string {
        if ($validacionAprobada) {
            return 'APROBADA';
        }

        if ($matricula && strtoupper((string) $matricula->estado) === 'MATRICULADO') {
            return 'APROBADA';
        }

        if (
            $saldoPendiente <= 0
            && in_array($estadoFactura, ['PAGADO', 'PAGADA', 'APROBADO'], true)
        ) {
            return 'APROBADA';
        }

        if (in_array($estadoFactura, ['PAGADO', 'PAGADA', 'APROBADO'], true)) {
            return 'APROBADA';
        }

        if (in_array($estadoFactura, ['ANULADA', 'ANULADO'], true)) {
            return 'RECHAZADA';
        }

        return 'PENDIENTE';
    }


    private const MARCA_VALIDACION_INSCRIPCION = 'VALIDACION_INSCRIPCION:APROBADA';


    private function resolverPagoFacturaAcademica(Factura $factura): ?Pago
    {
        $transaccion = $factura->transacciones->first();

        return $transaccion?->pago?->first();
    }


    private function solicitudValidacionAprobada(Factura $factura): bool
    {
        $transaccion = $factura->transacciones->first();
        $pago = $this->resolverPagoFacturaAcademica($factura);

        if ($pago && (int) $pago->idEstado === Status::ID_APROBADO && (float) $pago->excedente <= 0) {
            return true;
        }

        if (
            $transaccion
            && (int) $transaccion->idEstado === Status::ID_APROBADO
            && (!$pago || (float) $pago->excedente <= 0)
        ) {
            return true;
        }

        if ($pago && str_contains((string) ($pago->observacion ?? ''), self::MARCA_VALIDACION_INSCRIPCION)) {
            return true;
        }

        return false;
    }


    private function aplicarMarcaValidacionInscripcionEnPago(Pago $pago, ?string $observaciones = null): void
    {
        $notas = trim((string) ($observaciones ?? ''));
        $marca = self::MARCA_VALIDACION_INSCRIPCION;

        if ($this->solicitudValidacionAprobadaDesdePago($pago)) {
            if ($notas !== '' && !str_contains((string) $pago->observacion, $notas)) {
                $pago->observacion = trim((string) $pago->observacion . ' | ' . $notas);
            }

            return;
        }

        $pago->observacion = $notas !== ''
            ? $marca . ' | ' . $notas
            : $marca;
    }


    private function solicitudValidacionAprobadaDesdePago(Pago $pago): bool
    {
        return str_contains((string) ($pago->observacion ?? ''), self::MARCA_VALIDACION_INSCRIPCION);
    }
}
