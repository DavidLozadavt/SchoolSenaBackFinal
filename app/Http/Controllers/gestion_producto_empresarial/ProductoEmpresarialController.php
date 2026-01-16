<?php

namespace App\Http\Controllers\gestion_producto_empresarial;

use App\Http\Controllers\Controller;
use App\Mail\BillMailService;
use App\Mail\MailService;
use App\Models\AgregarPagoCuenta;
use App\Models\asignacionContratoClienteTransaccion;
use App\Models\ContratoCliente;
use App\Models\Factura;
use App\Models\IdentificationType;
use App\Models\MedioPago;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\ProductoEmpresarial;
use App\Models\SolicitudProducto;
use App\Models\Status;
use App\Models\SubCuentaPropia;
use App\Models\Tercero;
use App\Models\TipoPago;
use App\Models\TipoProductoEmpresarial;
use App\Models\TipoTercero;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use App\Models\Vinculacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;


class ProductoEmpresarialController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $producto = ProductoEmpresarial::all();
        return response()->json($producto);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $data = $request->all();

        $fechaCreacion = Carbon::now()->format('Y m d');
        $data['fecha_creacion'] = $fechaCreacion;


        if (!isset($data['idEstado'])) {
            $data['idEstado'] = Status::ID_ACTIVE;
        }

        $producto = new ProductoEmpresarial($data);
        $producto->save();


        return response()->json($producto, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


    public function getTipoProductosEmpresariales()
    {
        $tipoProducto = TipoProductoEmpresarial::all();
        return response()->json($tipoProducto);
    }


    public function storeTercero(Request $request)
    {


        DB::beginTransaction();

        try {


            $existingProveedor = Tercero::where('identificacion', $request->input('identificacion'))->first();


            if ($existingProveedor) {
                return response()->json($existingProveedor);
            }

            $tercero = new Tercero();
            $tercero->nombre = $request->input('nombre');
            $tercero->nombreContacto = $request->input('nombreContacto');
            $tercero->idTipoTercero = TipoTercero::CLIENTE;
            $tercero->idCompany = Session::get('company_id');
            $tercero->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $tercero->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');
            $tercero->idTipoIdentificacion = $request->input('idTipoIdentificacion');
            $tercero->identificacion = $request->input('identificacion');

            $tercero->email = $request->input('email');


            $tercero->direccion = $request->input('direccion');
            $tercero->telefono = $request->input('telefono');
            $tercero->digitoVerficacion = $request->input('digitoVerficacion');
            $tercero->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $tercero->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');

            $tercero->emailContacto = $request->input('emailContacto');


            $tercero->telefonoContacto = $request->input('telefonoContacto');
            $tercero->representanteLegal = $request->input('representanteLegal');

            $tercero->rutDocumento = $this->storeRutTercero($request);

            $tercero->save();

            DB::commit();

            return response()->json($tercero, 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }





    public function updateTercero(Request $request)
    {
        $idTercero = $request->input('idTercero');
        $idSolicitud = $request->input('idSolicitud');
        DB::beginTransaction();

        try {
            $tercero = Tercero::findOrFail($idTercero);

            $tercero->nombre = $request->input('nombre');
            $tercero->nombreContacto = $request->input('nombreContacto');

            $tercero->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $tercero->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');
            $tercero->idTipoIdentificacion = $request->input('idTipoIdentificacion');
            $tercero->identificacion = $request->input('identificacion');
            $tercero->email = $request->input('email');
            $tercero->direccion = $request->input('direccion');
            $tercero->telefono = $request->input('telefono');
            $tercero->digitoVerficacion = $request->input('digitoVerficacion');
            $tercero->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $tercero->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');
            $tercero->emailContacto = $request->input('emailContacto');
            $tercero->telefonoContacto = $request->input('telefonoContacto');
            $tercero->representanteLegal = $request->input('representanteLegal');


            $tercero->rutDocumento = $this->storeRutTercero($request);

            $tercero->save();

            $solicitud = SolicitudProducto::findOrFail($idSolicitud);
            $solicitud->idEstado = Status::ID_APROBADO;
            $solicitud->save();

            DB::commit();

            return response()->json($tercero, 200);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }



    private function storeRutTercero(Request $request, $default = true)
    {
        $rutaFactura = null;

        if ($default) {
            $rutaFactura = Tercero::RUTA_RUT_DEFAULT;
        }
        if ($request->hasFile('rutaRutFile')) {
            $rutaFactura =
                '/storage/' .
                $request
                ->file('rutaRutFile')
                ->store(Tercero::RUTA_RUT, ['disk' => 'public']);
        }
        return $rutaFactura;
    }


    public function getPlanesByProducts($id)
    {

        $planes = Plan::where('idProductoEmpresarial', $id)->get();

        if ($planes->isEmpty()) {
            return response()->json(['error' => 'Productos no encontrados.'], 404);
        }

        return response()->json($planes);
    }


    public function storeVinculacion(Request $request)
    {
        $idTercero = $request->input('idTercero');
        $idConexion = $request->input('idConexion');

        DB::beginTransaction();

        try {

            $tercero = Tercero::find($idTercero);

            if (!$tercero) {
                return response()->json(['error' => 'Tercero no encontrado.'], 404);
            }

            $plan = Plan::find($request->input('idPlan'));

            if (!$plan) {
                return response()->json(['error' => 'Plan no encontrado.'], 404);
            }


            $periodoMeses = $plan->periodoMeses;

            $vinculacion = new Vinculacion();
            $vinculacion->idTercero = $idTercero;
            $vinculacion->idProducto = $request->input('idProductoEmpresarial');
            $vinculacion->idEstado = $request->input('idEstado');


            if ($vinculacion->idEstado == 15) {
                $vinculacion->fechaEstadoInicial = $request->input('fechaInicio');
                $vinculacion->fechaEstadoFinal = $request->input('fechaFin');

                $conexion = $this->setConexion($tercero, $idConexion, $vinculacion->fechaEstadoInicial, $vinculacion->fechaEstadoFinal);
            } elseif ($vinculacion->idEstado == 16) {
                $fechaEstadoInicial = Carbon::now();
                $periodoMeses = $plan->periodoMeses;
                $fechaEstadoFinal = $fechaEstadoInicial->copy()->addMonths($periodoMeses);
                $vinculacion->fechaEstadoInicial = $fechaEstadoInicial;
                $vinculacion->fechaEstadoFinal = $fechaEstadoFinal;

                $conexion = $this->setConexion($tercero, $idConexion, $vinculacion->fechaEstadoInicial, $vinculacion->fechaEstadoFinal);
            }


            $vinculacion->idPlan = $plan->id;



            $subject = 'Información de tu Plan';
            $mailService = new BillMailService($subject, $plan, $vinculacion);
            Mail::to($tercero->email)->send($mailService);


            $mailService = new BillMailService($subject, $plan, $vinculacion);
            Mail::to($tercero->emailContacto)->send($mailService);



            $vinculacion->save();


            $contrato = new ContratoCliente();
            $contrato->idTercero =  $idTercero;
            $contrato->valor = $plan->valor;
            $contrato->numeroPagos = 1;
            $contrato->rutaContrato = $this->storeContratoCliente($request);
            $contrato->save();


            $transaccion = new Transaccion();
            $transaccion->fechaTransaccion = Carbon::now()->toDateString();
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->valor = $contrato->valor;
            $transaccion->idTipoTransaccion = TipoTransaccion::SERVICIO;
            $transaccion->idTipoPago = TipoPago::CREDITO;
            $transaccion->idEstado = Status::ID_ACTIVE;
            $transaccion->save();


            $asignacion = new asignacionContratoClienteTransaccion();
            $asignacion->idContrato = $contrato->id;
            $asignacion->idTransaccion = $transaccion->id;
            $asignacion->save();



            $pago = new Pago();
            $pago->idMedioPago = 4;
            $pago->valor = $contrato->valor;
            $pago->idTransaccion = $transaccion->id;
            $pago->idEstado = Status::ID_APROBADO;
            $pago->save();


            $pagoCuentaCliente = new AgregarPagoCuenta();
            $pagoCuentaCliente->idSubcuentaPropia = SubCuentaPropia::CLIENTES_NACIONALES;
            $pagoCuentaCliente->naturaleza = AgregarPagoCuenta::DEBITO;
            $pagoCuentaCliente->idTercero = $request->input('idTercero');
            $pagoCuentaCliente->idPago = $pago->id;
            $pagoCuentaCliente->save();

            $pagoCuentaBancos = new AgregarPagoCuenta();
            $pagoCuentaBancos->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
            $pagoCuentaBancos->naturaleza = AgregarPagoCuenta::CREDITO;
            $pagoCuentaBancos->idTercero = $request->input('idTercero');
            $pagoCuentaBancos->idPago = $pago->id;
            $pagoCuentaBancos->save();



            DB::commit();

            return response()->json($vinculacion, 201);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    private function setConexion($tercero, $idConexion, $fechaIncial, $fechaFinal)
    {
        $conexion = DB::table('source')->where('id', $idConexion)->first();
        if ($conexion) {
            $config = [
                'driver' => 'mysql',
                'host' => $conexion->h,
                'port' => $conexion->port,
                'database' => $conexion->nbd,
                'username' => $conexion->usc,
                'password' => $conexion->psc,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];
            Config::set('database.connections.otra_conexion', $config);


            $representanteLegal = $tercero->representanteLegal;
            $idTipoIdentificacion = $tercero->idTipoIdentificacion;
            $direccion = $tercero->direccion;
            $telefonoContacto = $tercero->telefonoContacto;
            $identificacion = $tercero->identificacion;
            $email = $tercero->email;
            $digitoVerficacion = $tercero->digitoVerficacion;
            $razonSocial = $tercero->nombre;


            $idPersona = DB::connection('otra_conexion')->table('persona')->insertGetId([
                'nombre1' => $representanteLegal,
                'apellido1' => $representanteLegal,
                'idTipoIdentificacion' => $idTipoIdentificacion,
                'identificacion' => $identificacion,
                'fechaNac' => '1999-01-01',
                'idCiudadNac' => 350,
                'idCiudadUbicacion' => 350,
                'direccion' => $direccion,
                'email' => $email,
                'celular' => $telefonoContacto,
            ]);


            $passwordHash = Hash::make('v1rtu4lt');

            $idUser = DB::connection('otra_conexion')->table('user')->insertGetId([
                'email' => $email,
                'contrasena' => $passwordHash,
                'idPersona' => $idPersona,
            ]);


            $idCompany = DB::connection('otra_conexion')->table('company')->insertGetId([
                'razonSocial' => $razonSocial,
                'email' => $email,
                'nit' => $identificacion,
                'rutaLogo' => '',
                'representanteLegal' => $representanteLegal,
                'digitoVerificacion' => $digitoVerficacion
            ]);

            $idRol = DB::connection('otra_conexion')->table('roles')->insertGetId([
                'name' => 'ADMIN_' . $razonSocial,
                'guard_name' => 'api',
                'idCompany' =>  $idCompany,
            ]);


            DB::connection('otra_conexion')->table('role_has_permissions')->insert([
                'permission_id' =>  1,
                'role_id' => $idRol,

            ]);

            DB::connection('otra_conexion')->table('role_has_permissions')->insert([
                'permission_id' => 2,
                'role_id' => $idRol,
            ]);

            DB::connection('otra_conexion')->table('role_has_permissions')->insert([
                'permission_id' => 3,
                'role_id' => $idRol,
            ]);



            $idActivateUser =  DB::connection('otra_conexion')->table('activation_company_users')->insertGetId([
                'idUser' => $idUser,
                'idEstado' => 1,
                'idCompany' => $idCompany,
                'fechaInicio' => $fechaIncial,
                'fechaFin' => $fechaFinal,
            ]);


            DB::connection('otra_conexion')->table('model_has_roles')->insertGetId([
                'model_type' => 'App\Models\ActivationCompanyUser',
                'model_id' =>  $idActivateUser,
                'role_id' => $idRol,
            ]);


            DB::connection('otra_conexion')->table('grupoGenerales')->insertGetId([
                'nombreGrupo' => 'GrupoGeneral_' . $razonSocial,
                'idUser' =>  $idUser,
                'idCompany' => $idCompany,
            ]);
        }
    }



    public function storeTerceroLanding(Request $request)
    {


        $idPlan = $request->input('idPlan');
        $idProductoEmpresarial = 1; //aqui hacerlo dinamico 

        DB::beginTransaction();

        try {
            $tercero = new Tercero();
            $tercero->nombre = $request->input('nombre');
            $tercero->nombreContacto = $request->input('nombreContacto');
            $tercero->idTipoTercero = TipoTercero::CLIENTE;
            $tercero->idCompany = 1;
            $tercero->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $tercero->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');
            $tercero->idTipoIdentificacion = IdentificationType::NIT;
            $tercero->identificacion = $request->input('identificacion');

            $tercero->email = $request->input('email');


            $tercero->direccion = $request->input('direccion');
            $tercero->telefono = $request->input('telefono');
            $tercero->digitoVerficacion = $request->input('digitoVerficacion');
            $tercero->responsableIva = empty($request->input('responsableIva')) ? 0 : $request->input('responsableIva');
            $tercero->retenciones = empty($request->input('retenciones')) ? 0 : $request->input('retenciones');

            $tercero->emailContacto = $request->input('emailContacto');


            $tercero->telefonoContacto = $request->input('telefonoContacto');
            $tercero->representanteLegal = $request->input('representanteLegal');


            $this->notificationStoreTerceroLanding();

            $tercero->save();


            $solicitud = new SolicitudProducto();
            $solicitud->idPlan = $idPlan;
            $solicitud->idProductoEmpresarial = $idProductoEmpresarial;
            $solicitud->idTercero = $tercero->id;
            $solicitud->idEstado = Status::ID_PENDIENTE;
            $solicitud->fecha = Carbon::now()->toDateTimeString();
            $solicitud->hora = Carbon::now()->format('H:i');

            $solicitud->save();




            $plan = Plan::where('id', $idPlan)->first();

            if (!$plan) {
                throw new \Exception("No se encontro el plan", 505);
            }



            $solicitud = SolicitudProducto::where('id', $solicitud->id)->first();

            if (!$solicitud) {
                throw new \Exception("No se encontro la solicitud", 505);
            }



            $productEmpresarial = ProductoEmpresarial::where('id', $idProductoEmpresarial)->first();

            if (!$productEmpresarial) {
                throw new \Exception("No se encontro el producto Empresarial", 505);
            }


            $subject = "Solicitud de Producto Empresarial";

            $message = "Estimado/a Gerente de Virtual Technology,\n\n";
            $message .= "Le informamos que se ha realizado una nueva solicitud de producto empresarial.\n\n";
            $message .= "Detalles de la solicitud:\n";
            $message .= "- Cliente: " . $tercero->nombreContacto . "\n";
            $message .= "- Producto: " . $productEmpresarial->nombreProducto . "\n";
            $message .= "- Plan: " . $plan->nombrePlan . "\n";
            $message .= "- Duración de plan: " . $plan->periodoMeses . "\n";
            $message .= "- Estado: " . $solicitud->estado->estado . "\n";
            $message .= "- Fecha: " . $solicitud->fecha . "\n";
            $message .= "- Hora: " . $solicitud->hora . "\n\n";
            $message .= "Por favor, revise la solicitud y tome las acciones necesarias.\n\n";
            $message .= "Atentamente,\n";
            $message .= "El equipo de Virtual Technology";



            $mailService = new MailService($subject, $message);
            Mail::to('gerente@virtualt.org')->send($mailService);


            $mailService = new MailService($subject, $message);
            Mail::to('virtualtsoftware@virtualt.org')->send($mailService);






            DB::commit();

            return response()->json($tercero, 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    private function notificationStoreTerceroLanding() {}


    public function getSolicitudesProductos()
    {
        $solicitud = SolicitudProducto::with('productoEmpresarial', 'estado', 'plan', 'tercero')->get();
        return response()->json($solicitud);
    }


    public function storeContratoProductoAlaMedida(Request $request)
    {

        DB::beginTransaction();

        try {

            $contrato = new ContratoCliente();
            $contrato->idTercero = $request->input('idTercero');
            $contrato->valor = $request->input('valor');
            $contrato->descripcion = $request->input('descripcion');
            $contrato->fechaInicialContrato = $request->input('fechaInicialContrato');
            $contrato->numeroPagos = $request->input('numeroPagos');
            $contrato->formaPago = $request->input('formaPago');
            $contrato->rutaContrato = $this->storeContratoCliente($request);
            $contrato->save();


            $transaccion = new Transaccion();
            $transaccion->fechaTransaccion = Carbon::now()->toDateString();
            $transaccion->hora = Carbon::now()->format('H:i');
            $transaccion->valor = $contrato->valor;
            $transaccion->idTipoTransaccion = TipoTransaccion::SERVICIO;
            $transaccion->idTipoPago = TipoPago::CREDITO;
            $transaccion->idEstado = Status::ID_ACTIVE;
            $transaccion->save();


            $asignacion = new asignacionContratoClienteTransaccion();
            $asignacion->idContrato = $contrato->id;
            $asignacion->idTransaccion = $transaccion->id;
            $asignacion->save();


            if ($contrato->numeroPagos != 1) {
                $pagoCuentaCliente = new AgregarPagoCuenta();
                $pagoCuentaCliente->idSubcuentaPropia = SubCuentaPropia::CLIENTES_NACIONALES;
                $pagoCuentaCliente->naturaleza = AgregarPagoCuenta::DEBITO;
                $pagoCuentaCliente->idTercero = $request->input('idTercero');
                $pagoCuentaCliente->idTransaccion = $transaccion->id;
                $pagoCuentaCliente->save();

                $pagoCuentaDesarrollo = new AgregarPagoCuenta();
                $pagoCuentaDesarrollo->idSubcuentaPropia = SubCuentaPropia::DESAROLLO_DE_SOFTWARE_A_LA_MEDIDA;
                $pagoCuentaDesarrollo->naturaleza = AgregarPagoCuenta::CREDITO;
                $pagoCuentaDesarrollo->idTercero = $request->input('idTercero');
                $pagoCuentaDesarrollo->idTransaccion = $transaccion->id;
                $pagoCuentaDesarrollo->save();
            }


            $pagosJson = $request->input('pagos');
            $pagos = json_decode($pagosJson, true);

            foreach ($pagos as $pagoData) {



                $pagoTotal = new Pago();
                $pagoTotal->valor = $pagoData['valorReal'];

                $pagoTotal->idTransaccion = $transaccion->id;
                $pagoTotal->idEstado = Status::ID_PENDIENTE;
                $pagoTotal->save();

                $pago = new Pago();


                $pago->valor = $pagoData['valor'];
                $pago->porcentaje = $pagoData['porcentaje'];
                $pago->idTransaccion = $transaccion->id;

                $pago->retencion = 'NO';
                $pago->idEstado = Status::ID_PENDIENTE;
                $pago->idPagoTotal = $pagoTotal->id;
                $pago->save();

                $pagoRetencion = new Pago();


                $pagoRetencion->valor = $pagoData['retencion'];
                $pagoRetencion->idTransaccion = $transaccion->id;

                $pagoRetencion->idEstado = Status::ID_PENDIENTE;
                $pagoRetencion->retencion = 'SI';
                $pagoRetencion->idPagoTotal = $pagoTotal->id;
                $pagoRetencion->save();
            }

            DB::commit();

            return response()->json($contrato, 201);
        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    private function storeContratoCliente(Request $request, $default = true)
    {
        $rutaFactura = null;

        if ($default) {
            $rutaFactura = ContratoCliente::RUTA_CONTRATO_CLIENTE_DEFAULT;
        }
        if ($request->hasFile('rutaContratoFile')) {
            $rutaFactura =
                '/storage/' .
                $request
                ->file('rutaContratoFile')
                ->store(ContratoCliente::RUTA_CONTRATO_CLIENTE, ['disk' => 'public']);
        }
        return $rutaFactura;
    }


    public function generarPDF($idPago, $idPago2, $idContrato)
    {
        try {

            $fechaActual = date('Y-m-d H:i:s');


            $contrato = ContratoCliente::where('id', $idContrato)->firstOrFail();
            $tercero = $contrato->tercero;


            $pago = Pago::where('id', $idPago)->firstOrFail();
            $pago->fechaCobro = $fechaActual;
            $pago->save();

            $pago2 = Pago::where('id', $idPago2)->firstOrFail();


            $transaccion = Transaccion::where('id', $pago->idTransaccion)->firstOrFail();

            $nCuenta = Pago::numeroC;


            $pdf = PDF::loadView('cuenta-cobro-pdf', [
                'contrato' => $contrato,
                'pago' => $pago,
                'pago2' => $pago2,
                'fechaActual' => $fechaActual,
                'nCuenta' => $nCuenta,
                'tercero' => $tercero,
                'transaccion' => $transaccion
            ]);

            return $pdf->download('cuenta-cobro.pdf');
        } catch (\Exception $e) {

            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }


    public function storeComprobantePago(Request $request)
    {

        $idPago = $request->input('idPago');
        $idPagoRetencion = $request->input('idPagoRetencion');
        $idPagoCompleto = $request->input('idPagoCompleto');

        $email = $request->input('email');
        $idContrato = $request->input('idContrato');
        $contrato = ContratoCliente::where('id', $idContrato)->firstOrFail();
        $tercero = $contrato->tercero;


        $pago2 = Pago::where('id', $idPagoCompleto)->firstOrFail();
        $pagoMensual = Pago::findOrFail($idPago);


        $pagoMensual->rutaComprobante = $this->storeComprobante($request);
        $pagoMensual->idEstado = Status::ID_APROBADO;
        $pagoMensual->idMedioPago = 4;
        $pagoMensual->fechaPago = $request->input('fecha');
        $pagoMensual->fechaReg = $request->input('fecha');
        $valorPago = $pagoMensual->valor;

        $pagoMensual->save();
        $valorPagoFormateado = number_format($valorPago, 2, '.', ',');

        $subject = "Confirmación de Pago Exitoso";
        $message = "Nos complace informarte que hemos registrado exitosamente tu pago en nuestra plataforma por un valor de $valorPagoFormateado.\n\n";
        $message .= "Si tienes alguna pregunta o necesitas asistencia adicional, no dudes en contactarnos.\n\n";
        $message .= "Atentamente,\n";
        $message .= "El equipo de Virtual Technology.\n";



        //envia recibo pago por email en pdf
        // $pdf = PDF::loadView('recibo-pago-pdf', ['contrato' => $contrato, 'pago' => $pagoMensual, 'pago2' => $pago2, 'tercero' => $tercero]);
        // $pdfOutput = $pdf->output();
        // $pdfFilePath = storage_path('app/public/recibo-pago.pdf');
        // file_put_contents($pdfFilePath, $pdfOutput);

        // $mailService = new MailService($subject, $message);


        // $mailService->attach($pdfFilePath, [
        //     'as' => 'recibo-pago.pdf',
        //     'mime' => 'application/pdf',
        // ]);


        // Mail::to($email)->send($mailService);


        // unlink($pdfFilePath);
        $mailService = new MailService($subject, $message);
        Mail::to($email)->send($mailService);

        $pagoCuentaCliente = new AgregarPagoCuenta();
        $pagoCuentaCliente->idSubcuentaPropia = SubCuentaPropia::CLIENTES_NACIONALES;
        $pagoCuentaCliente->naturaleza = AgregarPagoCuenta::CREDITO;
        $pagoCuentaCliente->idTercero = $tercero->id;
        $pagoCuentaCliente->idPago = $idPagoCompleto;
        $pagoCuentaCliente->save();

        $pagoCuentaRetencion = new AgregarPagoCuenta();
        $pagoCuentaRetencion->idSubcuentaPropia = SubCuentaPropia::RETENCION_10_PORCIENTO;
        $pagoCuentaRetencion->naturaleza = AgregarPagoCuenta::DEBITO;
        $pagoCuentaRetencion->idTercero = $tercero->id;
        $pagoCuentaRetencion->idPago = $idPagoRetencion;
        $pagoCuentaRetencion->save();


        $pagoCuentaBancos = new AgregarPagoCuenta();
        $pagoCuentaBancos->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
        $pagoCuentaBancos->naturaleza = AgregarPagoCuenta::DEBITO;
        $pagoCuentaBancos->idTercero = $tercero->id;
        $pagoCuentaBancos->idPago = $idPago;
        $pagoCuentaBancos->save();
    }


    public function storeComprobantePagoEfectivo(Request $request)
    {
        $idPago = $request->input('idPago');
        $idPagoRetencion = $request->input('idPagoRetencion');
        $idPagoCompleto = $request->input('idPagoCompleto');
        $email = $request->input('email');
        $idContrato = $request->input('idContrato');
        $contrato = ContratoCliente::where('id', $idContrato)->firstOrFail();
        $tercero = $contrato->tercero;

        $pago2 = Pago::where('id', $idPagoCompleto)->firstOrFail();
        $pagoMensual = Pago::findOrFail($idPago);
        $pagoMensual->idEstado = Status::ID_APROBADO;
        $pagoMensual->idMedioPago = 1;
        $pagoMensual->fechaPago = Carbon::now()->toDateString();
        $pagoMensual->fechaReg = Carbon::now()->toDateString();
        $valorPago = $pagoMensual->valor;
        $pagoMensual->save();

        $valorPagoFormateado = number_format($valorPago, 2, '.', ',');

        $subject = "Confirmación de Pago Exitoso";
        $message = "Nos complace informarte que hemos registrado exitosamente tu pago en nuestra plataforma por un valor de $valorPagoFormateado.\n\n";
        $message .= "Si tienes alguna pregunta o necesitas asistencia adicional, no dudes en contactarnos.\n\n";
        $message .= "Atentamente,\n";
        $message .= "El equipo de Virtual Technology.\n";


        // $pdf = PDF::loadView('recibo-pago-pdf', ['contrato' => $contrato, 'pago' => $pagoMensual, 'pago2' => $pago2, 'tercero' => $tercero]);
        // $pdfOutput = $pdf->output();
        // $pdfFilePath = storage_path('app/public/recibo-pago.pdf');
        // file_put_contents($pdfFilePath, $pdfOutput);


        // $mailService = new MailService($subject, $message);


        // $mailService->attach($pdfFilePath, [
        //     'as' => 'recibo-pago.pdf',
        //     'mime' => 'application/pdf',
        // ]);


        // Mail::to($email)->send($mailService);


        // unlink($pdfFilePath);
        $mailService = new MailService($subject, $message);
        Mail::to($email)->send($mailService);



        $pagoCuentaCliente = new AgregarPagoCuenta();
        $pagoCuentaCliente->idSubcuentaPropia = SubCuentaPropia::CLIENTES_NACIONALES;
        $pagoCuentaCliente->naturaleza = AgregarPagoCuenta::CREDITO;
        $pagoCuentaCliente->idTercero = $tercero->id;
        $pagoCuentaCliente->idPago = $idPagoCompleto;
        $pagoCuentaCliente->save();

        $pagoCuentaRetencion = new AgregarPagoCuenta();
        $pagoCuentaRetencion->idSubcuentaPropia = SubCuentaPropia::RETENCION_10_PORCIENTO;
        $pagoCuentaRetencion->naturaleza = AgregarPagoCuenta::DEBITO;
        $pagoCuentaRetencion->idTercero = $tercero->id;
        $pagoCuentaRetencion->idPago = $idPagoRetencion;
        $pagoCuentaRetencion->save();


        $pagoCuentaBancos = new AgregarPagoCuenta();
        $pagoCuentaBancos->idSubcuentaPropia = SubCuentaPropia::BANCOS_NACIONALES;
        $pagoCuentaBancos->naturaleza = AgregarPagoCuenta::DEBITO;
        $pagoCuentaBancos->idTercero = $tercero->id;
        $pagoCuentaBancos->idPago = $idPago;
        $pagoCuentaBancos->save();
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





    //cuentas por cobrar
    public function getCuentasPendientes()
    {
        $facturas = Factura::with('transacciones.pago', 'tercero', 'detalles')
            ->orderBy('created_at', 'desc')
            ->get();

        $facturasCXP = $facturas->filter(function ($factura) {
            return $factura->transacciones->contains(function ($transaccion) {
                return $transaccion->tipoCartera === 'CXC';
            });
        });

        $facturasCXP->each(function ($factura) {


            $transaccionesCXC = $factura->transacciones->where('tipoCartera', 'CXC');

            foreach ($transaccionesCXC as $transaccion) {


                $totalPagado = $transaccion->pago
                    ->where('idEstado', 5)
                    ->sum('valor');


                $transaccion->faltante = max(0, $transaccion->valor - $totalPagado);
            }
        });

        return response()->json($facturasCXP->values());
    }





    //guardar abono de cuenta por cobrar
    public function storeAbonoPagoCuentaCobrar(Request $request)
    {
        $valorAbono = $request->input('valorAbono');
        $idTransaccion = $request->input('idTransaccion');

        $transaccion = Transaccion::with('pago')->find($idTransaccion);

        $pagoPrincipal = $transaccion->pago->first();

        $pago = new Pago();
        $pago->rutaComprobante = $this->storeComprobante($request);
        $pago->valor = $valorAbono;
        $pago->fechaPago = Carbon::now()->format('Y-m-d');
        $pago->fechaReg = Carbon::now()->format('Y-m-d');
        $pago->idEstado = Status::ID_APROBADO;
        $pago->idTransaccion = $idTransaccion;
        $pago->idPagoTotal = $pagoPrincipal?->id;
        $pago->idMedioPago = MedioPago::TRANSFERENCIA;

        $pago->save();

        $totalPagado = Pago::where('idTransaccion', $idTransaccion)
            ->where('idEstado', Status::ID_APROBADO)
            ->sum('valor');

        if ($totalPagado >= $transaccion->valor) {
            $transaccion->idEstado = Status::ID_APROBADO;
            $transaccion->save();
        }

        return response()->json([
            'transaccion' => $transaccion,
            'totalPagado' => $totalPagado,
            'estadoActualizado' => $totalPagado >= $transaccion->valor
        ]);
    }


    //obtener pagos abonos cuentas por cobrar 


    public function getPagosAbonosCuentasPorCobrar($idTransaccion)
    {
        $pagosAbonosCuentasPorCobrar = Pago::where('idTransaccion', $idTransaccion)
            ->whereNotNull('idPagoTotal')
            ->get();
        return response()->json($pagosAbonosCuentasPorCobrar);
    }


    //genear cuenta cobro pago cuentas por cobrar
    public function generarCuentaCobroPagoCuentasPorCobrar($idTransaccion, $idTercero)
    {
        try {

            $fechaActual = date('Y-m-d H:i:s');
            $tercero = Tercero::where('id', $idTercero)->firstOrFail();
            $transaccion = Transaccion::where('id', $idTransaccion)->firstOrFail();
            $transaccion->fechaCobro = $fechaActual;
            $transaccion->save();

            $pdf = PDF::loadView('cuenta-cobro-cxc-pdf', [
                'tercero' => $tercero,
                'transaccion' => $transaccion
            ]);

            return $pdf->download('cuenta-cobro-cxc.pdf');
        } catch (\Exception $e) {

            return response()->json(['error' => 'Error al generar el PDF: ' . $e->getMessage()], 500);
        }
    }
}
