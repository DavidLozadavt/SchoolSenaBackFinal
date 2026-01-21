<?php

use App\Http\Controllers\AgendaEscenarioController;
use App\Http\Controllers\EstadoViajeController;
use App\Http\Controllers\gestion_transporte\ObservacionViajeController;
use App\Http\Controllers\IdentificationTypeController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\LugarController;
use App\Http\Controllers\WompiController;
use App\Http\Controllers\CiudadController;
use App\Http\Controllers\auth\AuthController;
use App\Http\Controllers\auth\AuthFactusController;
use App\Http\Controllers\auth\UserController;
use App\Http\Controllers\auth\LoginController;
use App\Http\Controllers\ClaseVehiculoController;
use App\Http\Controllers\ComprasWebController;
use App\Http\Controllers\PuntoVentaController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\DetalleRevisionController;
use App\Http\Controllers\FacturacionElectronicaController;
use App\Http\Controllers\gestion_rol\RolController;
use App\Http\Controllers\gestion_pago\PagoController;
use App\Http\Controllers\gestion_plan\PlanController;
use App\Http\Controllers\gestion_nomina\AreaController;
use App\Http\Controllers\gestion_nomina\SedeController;
use App\Http\Controllers\gestion_nomina\NominaController;
use App\Http\Controllers\gestion_chat\GrupoChatController;
use App\Http\Controllers\gestion_chat\TipoGrupoController;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use App\Http\Controllers\gestion_chat\ComentarioController;
use App\Http\Controllers\gestion_compras\ComprasController;
use App\Http\Controllers\gestion_empresa\CompanyController;
use App\Http\Controllers\gestion_nomina\ComisionController;
use App\Http\Controllers\gestion_nomina\VacacionController;
use App\Http\Controllers\gestion_proceso\ProcesoController;
use App\Http\Controllers\gestion_tercero\TerceroController;
use App\Http\Controllers\gestion_transporte\RutaController;
use App\Http\Controllers\gestion_nomina\HoraExtraController;
use App\Http\Controllers\gestion_nomina\TarifaArlController;
use App\Http\Controllers\gestion_tareas\BoardTaskController;
use App\Http\Controllers\gestion_transporte\ViajeController;
use App\Http\Controllers\gestion_afiliacion\MarcasController;
use App\Http\Controllers\gestion_conexion\ConexionController;
use App\Http\Controllers\gestion_reportes\ReportesController;
use App\Http\Controllers\gestion_tipopago\TipoPagoController;
use App\Http\Controllers\gestion_transporte\ReportController;
use App\Http\Controllers\gestion_transporte\TicketController;
use App\Http\Controllers\gestion_afiliacion\ModelosController;
use App\Http\Controllers\gestion_aporte\AporteSocioController;
use App\Http\Controllers\gestion_nomina\CentroCostoController;
use App\Http\Controllers\gestion_mediopago\MedioPagoController;
use App\Http\Controllers\gestion_nomina\TipoIncapacidadController;
use App\Http\Controllers\gestion_afiliacion\TipoVehiculoController;
use App\Http\Controllers\gestion_chat\ComentarioArchivosController;
use App\Http\Controllers\gestion_rol_permisos\AsignacionRolPermiso;
use App\Http\Controllers\gestion_transporte\AgendarViajeController;
use App\Http\Controllers\gestion_nomina\SolicitudVacacionController;
use App\Http\Controllers\gestion_afiliacion\TipoAfiliacionController;
use App\Http\Controllers\gestion_contratacion\ContratacionController;
use App\Http\Controllers\gestion_notificacion\NotificacionController;
use App\Http\Controllers\gestion_chat\AsignacionComentariosController;
use App\Http\Controllers\gestion_nomina\ConfiguracionNominaController;
use App\Http\Controllers\gestion_tipo_contrato\TipoContratoController;
use App\Http\Controllers\gestion_tipo_producto\TipoProductoController;
use App\Http\Controllers\gestion_chat\AsignacionParticipanteController;
use App\Http\Controllers\gestion_clase_producto\ClaseProductoController;
use App\Http\Controllers\gestion_tipo_documento\TipoDocumentoController;
use App\Http\Controllers\gestion_afiliacion\AfiliacionVehiculoController;
use App\Http\Controllers\gestion_nomina\BonificacionController;
use App\Http\Controllers\gestion_nomina\SolicitudIncLicPersonaController;
use App\Http\Controllers\gestion_nomina\ConfiguracionHorasExtraController;
use App\Http\Controllers\gestion_nomina\ConfiguracionLicenciaController;
use App\Http\Controllers\gestion_tipotransaccion\TipoTransaccionController;
use App\Http\Controllers\gestion_transporte\DocumentoAlcoholemiaController;
use App\Http\Controllers\gestion_transporte\ConfiguracionVehiculoController;
use App\Http\Controllers\gestion_nomina\HistorialConfiguracionNominaController;
use App\Http\Controllers\gestion_nomina\ObservacionSolicitudVacacionController;
use App\Http\Controllers\gestion_nomina\ObservacionSolicitudIncLicPerController;
use App\Http\Controllers\gestion_nomina\OtrasDeduccionesController;
use App\Http\Controllers\gestion_nomina\ReemplazoController;
use App\Http\Controllers\gestion_producto_empresarial\ProductoEmpresarialController;
use App\Http\Controllers\gestion_reportes\ReporteSuperIntendenciaController;
use App\Http\Controllers\gestion_seguridad_social\EntidadesSeguridadSocialController;
use App\Http\Controllers\gestion_transporte\AutorizacionController;
use App\Http\Controllers\gestion_usuario\UserController as Gestion_usuarioUserController;
use App\Http\Controllers\MultimediaHistoriasController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\VentasServicioController;
use App\Http\Controllers\AsignacionDetalleRevisionVehiculoController;
use App\Http\Controllers\ConfiguracionMantenimientoController;
use App\Http\Controllers\DescuentoPlanillaController;
use App\Http\Controllers\AsignacionDescuentoPlanillaController;
use App\Http\Controllers\gestion_nomina\GrupoNominaController;
use App\Http\Controllers\ReporteObservacionesRevisionController;
use App\Http\Controllers\ReservaViajeController;
use App\Http\Controllers\gestion_productos\ConfiguracionProductosController;
use App\Http\Controllers\gestion_pedidos\GestionPedidosController;
use App\Http\Controllers\gestion_productos\CategoriasProController;
use App\Http\Controllers\gestion_productos\MedidasProController;
use App\Http\Controllers\gestion_almacen\AlmacenController;
use App\Http\Controllers\gestion_almacen\SolicitudProductoAlmacenController;
use App\Http\Controllers\EscenarioController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\TipoServicioController;
use App\Http\Controllers\CategoriaServicioController;
use App\Http\Controllers\gestion_aporte\PolizasController;
use App\Http\Controllers\gestion_centros_formacion\CentrosFormacionController;
use App\Http\Controllers\gestion_ventas\ResponsableServicioController;
use App\Http\Controllers\gestion_programas_academicos\PensumController;
use App\Http\Controllers\gestion_regional\RegionalController;
use App\Http\Controllers\PeriodosController;
use App\Http\Controllers\gestion_jornadas\JornadaController;

use App\Http\Controllers\gestion_programas_academicos\NivelesProgramaController;
use App\Http\Controllers\gestion_sede_institucional\SedeInstitucionalController;
use App\Http\Controllers\InfraestructuraController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);


Route::group([
    'middleware' => 'api',
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('user', [AuthController::class, 'getUser']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('active_users', [AuthController::class, 'getActiveUsers']);
    Route::post('set_company', [AuthController::class, 'setCompany']);
    Route::post('roles', [AuthController::class, 'getRoles']);
    Route::post('permissions', [AuthController::class, 'getPermissions']);
    Route::get('user_mobile', [AuthController::class, 'getUserAppMobile']);
    Route::get('get_users_and_groups', [Gestion_usuarioUserController::class, 'getUsersAndGroups']);
});

Route::resource('roles', RolController::class);


Route::get('list_companies', [CompanyController::class, 'index']);
Route::post('company_update', [CompanyController::class, 'update']);


//permisos
Route::get('permisos', [AsignacionRolPermiso::class, 'index']);
Route::get('permisos_rol', [AsignacionRolPermiso::class, 'permissionsByRole']);
Route::put('asignar_rol_permiso', [AsignacionRolPermiso::class, 'assignFunctionality']);

// notificaciones
Route::resource('notificaciones', NotificacionController::class);
Route::put('notificaciones/read/{id}', [NotificacionController::class, 'read']);

// proceso
Route::resource('procesos', ProcesoController::class);

// tipo documento
Route::resource('tipo_documentos', TipoDocumentoController::class);
// medio pagos
Route::resource('medio_pagos', MedioPagoController::class);
// tipo pagos
Route::resource('tipo_pagos', TipoPagoController::class);
// tipo transaccion
Route::resource('tipo_transacciones', TipoTransaccionController::class);

// traer listado de los usuario por empresa
Route::get('lista_usuarios', [Gestion_usuarioUserController::class, 'getUsers']);
Route::get('lista_usuarios_paginado', [Gestion_usuarioUserController::class, 'getUsersPaginated']);

//gestion de usario contratados
Route::resource('usuarios', Gestion_usuarioUserController::class);
Route::post('update_user/{id}', [Gestion_usuarioUserController::class, 'updateUser']);

//actuliza los datos de la persona autenticada
Route::post('update_person', [Gestion_usuarioUserController::class, 'updatePersona']);
Route::post('update_status_user/{id}', [Gestion_usuarioUserController::class, 'updateStatusUser']);





Route::get('users_company', [CompanyController::class, 'getUsersCompany']);


Route::put('asignar_roles', [Gestion_usuarioUserController::class, 'asignation']);

// Gestion Contratación
Route::get('contrato-tipos-identificacion', [ContratacionController::class, 'tiposIdentificacion']);
Route::get('contrato-tipos-contrato', [ContratacionController::class, 'tiposContrato']);
Route::get('contrato-persona/{identificacion}', [ContratacionController::class, 'getPersonaByIdentificacion']);
Route::post('contrato-persona', [ContratacionController::class, 'storePersona']);
Route::post('update_contrato_persona/{id}', [ContratacionController::class, 'updatePersonaContrato']);
Route::post('contrato', [ContratacionController::class, 'storeContrato']);
Route::post('update_contrato/{id}', [ContratacionController::class, 'udapteContrato']);
Route::get('contrato-tipo-documento', [ContratacionController::class, 'tipoDocumento']);
Route::get('contrato-roles', [ContratacionController::class, 'getRoles']);
Route::get('contratos', [ContratacionController::class, 'getAllContratos']);
Route::get('contrato/{identificacion}', [ContratacionController::class, 'getContratoByIdentificacion']);
Route::get('contrato_active/{identificacion}', [ContratacionController::class, 'getContratoByIdentificacionActive']);
Route::get('contrato_by_user', [ContratacionController::class, 'getContratoByPersonaLogueada'])->middleware('api');


Route::get('contrato_by_id/{id}', [ContratacionController::class, 'getContratoById']);
Route::get('contratos_by_id', [ContratacionController::class, 'getOneContratoById']);
Route::post('contrato-documento', [ContratacionController::class, 'storeDocumentoContrato']);
Route::post('update_contrato_documento', [ContratacionController::class, 'updateDocumentosContrato']);

//interrumpir y extender contrato
Route::post('interrumpir_contrato', [ContratacionController::class, 'interrumpirContrato']);
Route::post('extender_contrato', [ContratacionController::class, 'extenderContrato']);
Route::post('update_documento_contrato', [ContratacionController::class, 'updateDocumentoContrato']);
Route::post('delete_documento_contrato', [ContratacionController::class, 'deleteDocumentoContrato']);
Route::get('bancos', [ContratacionController::class, 'bancos']);
Route::post('store_banco', [ContratacionController::class, 'storeBanco']);
Route::get('tipos_terminacion_contrato', [ContratacionController::class, 'tiposTerminacionContrato']);
Route::get('actividades_riesgo_profesional', [ContratacionController::class, 'getActividadesRiesgoProfesional']);
Route::post('store_actividades_riesgo_profesional', [ContratacionController::class, 'storeActividadeRiesgoProfesional']);
Route::post('actualizar_entidad/{id}', [ContratacionController::class, 'updateEntidadSeguridadSocial']);


//GestionTipoContrato
Route::apiResource('tipo_contrato', TipoContratoController::class);

//gestion ubicaciones
Route::get('departamentos', [DepartamentoController::class, 'index']);
Route::get('ciudades/departamento/{idDepartamento}', [CiudadController::class, 'byDepartamento']);
Route::get('ciudades', [CiudadController::class, 'ciudades']);

//Gestion de pagos
Route::get('pagos_pendientes', [PagoController::class, 'getPagosPendientes']);
Route::get('all_pagos', [PagoController::class, 'getAllPagos']);
Route::get('pago_by_identificacion', [PagoController::class, 'getPagoByIdentificacion']);
Route::get('pagos_by_identificacion', [PagoController::class, 'getPagosByIdentificacion']);
Route::post('pago_mensual', [PagoController::class, 'pagoMensual']);
Route::get('documentos/{id}', [PagoController::class, 'documentosPago']);
Route::post('documentos_pago', [PagoController::class, 'cargarDocumentosPago']);
Route::get('certificacion_bancaria/{id}', [PagoController::class, 'getCertificacionBancaria']);
Route::get('comprobante', [PagoController::class, 'getComprobante']);
//endpoint para mostrar los documentos que se deben subir para el proceso de pago
Route::get('documentos_mensuales', [PagoController::class, 'getDocumentosPago']);
//endpoint para traer los documentos de pagos mensuales para revision
Route::get('documentos_for_revision', [PagoController::class, 'getDocumentosForRevision']);
Route::get('pago_by_id', [PagoController::class, 'getPagoById']);
Route::get('observaciones_documentos', [PagoController::class, 'getDocumentosReprobados']);

//estado de documentos de pago
Route::post('update_documento_reprobado', [PagoController::class, 'updateDocumentoPagoReprobado']);
Route::post('aprobar_documento_estado', [PagoController::class, 'updateEstadoDocumentoPago']);
Route::post('rechazo_documento_estado', [PagoController::class, 'rechazarDocumentoEstado']);

//solicitudes productos almacen
Route::get('get_solicitudes_almacen', [SolicitudProductoAlmacenController::class, 'getSolictidesProductosAlmacen']);
Route::get('trazabilidad_producto_almacen/{id}', [SolicitudProductoAlmacenController::class, 'getTrazabilidadSolicitud']);
Route::post('aprobar_producto_almacen/{id}', [SolicitudProductoAlmacenController::class, 'aprobarProductoAlmacen']);
Route::post('rechazar_producto_almacen', [SolicitudProductoAlmacenController::class, 'rechazarProductoAlmacen']);

//pagos adiccionales
Route::post('store_pago_adicional', [PagoController::class, 'storePagoAdicional']);
Route::put('update_pago_adicional/{id}', [PagoController::class, 'updatePagoAdicional']);
Route::delete('delete_pago_adicional/{id}', [PagoController::class, 'destroy']);
Route::get('pagos_adicionales/{id}/{idPago}', [PagoController::class, 'getPagosAdicionales']);
Route::post('update_valor_adicional', [PagoController::class, 'updateValorAdicional']);
Route::get('pagos_adicionales_activos', [PagoController::class, 'getPagosAdicionalesActivos']);


//gestion de compras y proveedores
Route::get('proveedor_by_nit/{nit}', [ComprasController::class, 'getProveedorByNiT']);
Route::post('store_proveedor', [ComprasController::class, 'storeProveedor']);
Route::post('store_factura', [ComprasController::class, 'storeFactura']);
Route::post('store_producto', [ComprasController::class, 'storeProducto']);
Route::post('store_producto_inventario', [ComprasController::class, 'storeProductoInventario']);
Route::post('forma_pago_factura', [ComprasController::class, 'storeFormPagoFactura']);
Route::get('clases', [ComprasController::class, 'clases']);
Route::get('facturas', [ComprasController::class, 'getFacturas']);
Route::post('store_pago_factura', [ComprasController::class, 'storePagoFactura']);
Route::get('products_select', [ComprasController::class, 'getProductsForSelect']);


//tercero
Route::apiResource('terceros', TerceroController::class);
Route::get('buscar_tercero', [TerceroController::class, 'buscarTercero']);


//socios
Route::get('get_socios', [TerceroController::class, 'getSocios']);
Route::post('store_socio', [TerceroController::class, 'storeSocio']);


Route::post('store_subcuenta_propia', [ComprasController::class, 'storeSubCuentaPropia']);
Route::get('subcuentas_by_id/{id}', [ComprasController::class, 'getSubCuentas']); //by cuenta id
Route::get('cuentas_by_id/{id}', [ComprasController::class, 'getCuentas']);  //by grupo id
Route::get('grupos_by_id/{id}', [ComprasController::class, 'getGrupos']);  //by clase id
Route::get('subcuentas_by_code', [ComprasController::class, 'getSubCuentaPropiasByCode']);
Route::get('subcuentas_propias', [ComprasController::class, 'getSubCuentasPropias']);
Route::delete('delete_subcuenta_propia/{id}', [ComprasController::class, 'deleteSubcuentaPropia']);
Route::put('update_subcuenta_propia/{id}', [ComprasController::class, 'updateSubCuentaPropia']);






Route::apiResource('tipo_productos', TipoProductoController::class);
Route::get('tipo_productos_by_id/{id}', [TipoProductoController::class, 'getTipoProductos']);

Route::apiResource('clase_productos', ClaseProductoController::class);

Route::apiResource('plan', PlanController::class);

Route::apiResource('producto_empresarial', ProductoEmpresarialController::class);
Route::apiResource('conexiones_source', ConexionController::class);


Route::get('tipo_productos_empresariales', [ProductoEmpresarialController::class, 'getTipoProductosEmpresariales']);
Route::post('store_tercero', [ProductoEmpresarialController::class, 'storeTercero']);
Route::post('store_producto_medida', [ProductoEmpresarialController::class, 'storeContratoProductoAlaMedida']);
Route::post('generar_cuenta_cobro/{idPago}/{idPago2}/{idContrato}', [ProductoEmpresarialController::class, 'generarPDF']);
Route::post('store_comprobante_pago', [ProductoEmpresarialController::class, 'storeComprobantePago']);
Route::post('store_comprobante_pago_efectivo', [ProductoEmpresarialController::class, 'storeComprobantePagoEfectivo']);

//cuentas por pagar

Route::post('update_tercero', [ProductoEmpresarialController::class, 'updateTercero']);
Route::post('store_vinculacion', [ProductoEmpresarialController::class, 'storeVinculacion']);
Route::post('store_tercero_landing', [ProductoEmpresarialController::class, 'storeTerceroLanding']);
Route::get('planes_by_products/{id}', [ProductoEmpresarialController::class, 'getPlanesByProducts']);
Route::get('solicitudes_productos', [ProductoEmpresarialController::class, 'getSolicitudesProductos']);
Route::post('store_abono_pago_cuenta_cobrar', [ProductoEmpresarialController::class, 'storeAbonoPagoCuentaCobrar']);
Route::get('pagos_abonos_cuentas_por_cobrar/{id}', [ProductoEmpresarialController::class, 'getPagosAbonosCuentasPorCobrar']);
Route::post('generar_cuenta_cobro_cxc/{idTransaccion}/{idTercero}', [ProductoEmpresarialController::class, 'generarCuentaCobroPagoCuentasPorCobrar']);

//cuentas por cobrar
Route::get('cuentas_pendientes', [ProductoEmpresarialController::class, 'getCuentasPendientes']);

//wompi
Route::group([], function () {

    //
    Route::get('get_tokens', [WompiController::class, 'getTokens']);
    //terminos y condciones
    Route::get('get_permalink', [WompiController::class, 'getPermalink']);
    //token
    Route::get('get_only_acceptance_token', [WompiController::class, 'getOnlyAcceptanceToken']);
    //obteiene el token con la dmas informacion personal
    Route::get('get_all_data_with_acceptance_token', [WompiController::class, 'getAllDataWithAcceptanceToken']);

    Route::get('get_financial_institutions', [WompiController::class, 'getFinancialInstitutions']);
    //rasteras informacion devuelve datos de la transacioj
    Route::get('find_transaction_by_id/{idTransaction}', [WompiController::class, 'findTransactionById']);
    //trasnacion en si
    Route::post('transaction_pse', [WompiController::class, 'makePSEPayment']);
    Route::post('cryptographic_hash', [WompiController::class, 'getCryptoGragraphicHash']);
});


//gestion aporte socios
Route::post('store_producto_socios', [AporteSocioController::class, 'storeAporte']);

//board tasks
Route::post('store_board', [BoardTaskController::class, 'storeBoard']);
Route::post('update_board/{id}', [BoardTaskController::class, 'updateBoard']);
Route::get('get_boards', [BoardTaskController::class, 'getBoards']);
Route::get('get_my_boards', [BoardTaskController::class, 'getMyBoards']);
Route::delete('delete_board/{id}', [BoardTaskController::class, 'deleteBoard']);

Route::post('assign_board', [BoardTaskController::class, 'assignBoard']);
Route::post('delete_assign_board', [BoardTaskController::class, 'deleteAssingBoard']);


Route::get('get_persons_by_board/{id}', [BoardTaskController::class, 'getPersonsByBoard']);
Route::get('get_persons_to_assing_board/{id}', [BoardTaskController::class, 'getPersonsToAssign']);

Route::post('store_list/{id}', [BoardTaskController::class, 'storeList']);
Route::get('get_list_by_board/{id}', [BoardTaskController::class, 'getListByBoard']);
Route::put('update_list/{id}', [BoardTaskController::class, 'updateList']);
Route::delete('delete_list/{id}', [BoardTaskController::class, 'deleteList']);

Route::post('store_card/{id}', [BoardTaskController::class, 'storeCard']);
Route::put('update_card/{id}', [BoardTaskController::class, 'updateCard']);
Route::delete('delete_card/{id}', [BoardTaskController::class, 'deleteCard']);
Route::get('get_cards_by_list/{id}', [BoardTaskController::class, 'getCardsByList']);
Route::get('get_card/{id}', [BoardTaskController::class, 'getCardBy']);

Route::post('store_ckeklist/{id}', [BoardTaskController::class, 'storeCheckList']);
Route::put('update_checklist/{id}', [BoardTaskController::class, 'updateCheckList']);
Route::get('get_cheklist_by_card/{id}', [BoardTaskController::class, 'getCheklistByCard']);
Route::get('get_cheklist_item_by_check/{id}', [BoardTaskController::class, 'getItemCheckListByIdCheck']);
Route::post('store_item_checklist/{id}', [BoardTaskController::class, 'storeItemChecklist']);
Route::post('check_descheck_item/{id}', [BoardTaskController::class, 'checkAndDeschekItem']);
Route::delete('delete_check_list/{id}', [BoardTaskController::class, 'deleteCheckList']);

Route::delete('delete_check_item/{id}', [BoardTaskController::class, 'deleteCheckItem']);
Route::put('update_item_check/{id}', [BoardTaskController::class, 'updateItemCheck']);


Route::get('get_files_by_card/{id}', [BoardTaskController::class, 'getFilesByCard']);
Route::post('store_files_card', [BoardTaskController::class, 'storeFilesCard']);
Route::delete('delete_file_card/{id}', [BoardTaskController::class, 'deleteFileCard']);

Route::post('assign_card', [BoardTaskController::class, 'assignCardUser']);
Route::get('get_users_card/{id}', [BoardTaskController::class, 'getUsersCard']);
Route::get('get_users_for_assign_card/{idBoard}/{idCard}', [BoardTaskController::class, 'getUsersForAssignCard']);
Route::delete('delete_assign_card/{id}', [BoardTaskController::class, 'deleteAssingCard']);

Route::get('get_detail_card/{id}', [BoardTaskController::class, 'getDetailCard']);
Route::post('store_card_datail/{id}', [BoardTaskController::class, 'storeCardDetail']);
Route::post('update_card_datail/{id}', [BoardTaskController::class, 'updateCardDetail']);
Route::post('complete_check_date/{id}', [BoardTaskController::class, 'completeDateCard']);
Route::delete('delete_date_card/{id}', [BoardTaskController::class, 'deleteDateCard']);


Route::post('assign_check_item', [BoardTaskController::class, 'assignCheckItemdUser']);
Route::post('store_check_item_detail/{id}', [BoardTaskController::class, 'storeCheckItemDetail']);
Route::get('get_users_item_check/{id}', [BoardTaskController::class, 'getUsersItemCheck']);
Route::get('get_detail_check_item/{id}', [BoardTaskController::class, 'getDetailItemCheck']);

//comentarios
Route::get('get_comment_check_item/{id}', [BoardTaskController::class, 'getCommentCheeckItem']);
Route::post('store_comment_item_check', [BoardTaskController::class, 'storeCommentItemCheck']);
Route::delete('delete_comment_item_check/{id}', [BoardTaskController::class, 'deleteCommentItemCheck']);

Route::post('store_comment_response', [BoardTaskController::class, 'storeResponseComment']);
Route::post('store_comment_sub_response', [BoardTaskController::class, 'storeSubResponseComment']);

Route::post('store_mencion_check_item', [BoardTaskController::class, 'sendEmailMentionCheckList']);
Route::post('store_mencion_comment', [BoardTaskController::class, 'sendEmailMentionComment']);
Route::post('store_mencion_comment_reponse', [BoardTaskController::class, 'sendEmailMentionCommentResponse']);



//elimima la fecha de un item check
Route::delete('delete_item_check_detail/{id}', [BoardTaskController::class, 'deleteItemCheckDetail']);
Route::delete('delete_user_item_check/{id}', [BoardTaskController::class, 'deleteUserItemCheck']);

Route::post('store_image_for_board', [BoardTaskController::class, 'storeImageForBoard']);
Route::get('get_images_for_board', [BoardTaskController::class, 'getImageBoards']);

Route::group(['auth:sanctum'], function () {
    Route::apiResource('/comentarios', ComentarioController::class)->only(['index']);

    Route::post('/send_message_between_two_users/{idUser}/comments', [ComentarioController::class, 'addCommentOneToOne']);

    Route::get('get_comments_user_to_user/{idUser}', [ComentarioController::class, 'getCommentsOneToOne']);
    Route::get('get_comments_by_grupo/{idGrupo}', [ComentarioController::class, 'getCommentsByGrupo']);
    Route::post('send_message_to_group/{idGroup}', [ComentarioController::class, 'addCommentGroup']);

    Route::apiResource('comentario_archivos', ComentarioArchivosController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('comentario_archivos/{id}', [ComentarioArchivosController::class, 'update']);

    Route::apiResource('groups', GrupoChatController::class);
    Route::apiResource('type_groups', TipoGrupoController::class);
    Route::apiResource('asignation_comments', AsignacionComentariosController::class);

    Route::apiResource('participant_assignment', AsignacionParticipanteController::class);
    Route::get('view_message_by_group/{idGrupo}', [ComentarioController::class, 'validateUserViewMessage']);
    Route::post('send_comment_to_group_or_user', [ComentarioController::class, 'sendCommentMultipleGroupsOrUsers']);
    Route::post('store_grupo', [ComentarioController::class, 'storeGrupo']);



    Route::post('auth/pusher', [ComentarioController::class, 'authorizationPusher']);
    Route::get('download_file/{idComentarioArchivo}', [ComentarioArchivosController::class, 'downloadFile']);
});

//configuracion repetir una card
Route::get('get_configuration_repeat', [BoardTaskController::class, 'getConfigurationRepeat']);
Route::post('store_configuration_repeat/{id}', [BoardTaskController::class, 'storeConfigurationRepeat']);
Route::put('delete_configuration_repeat/{id}', [BoardTaskController::class, 'deleteConfigurationRepeat']);


//archivar o desarchivar una card
Route::post('store_archive_card/{id}', [BoardTaskController::class, 'archivateCard']);
Route::post('store_unarchive_card/{id}', [BoardTaskController::class, 'unarchiveCard']);


//Reportes de tareas
Route::post('get_report_month', [ReportesController::class, 'getReportMonthTasks']);

Route::group([
    'middleware' => 'api',
], function () {
    Route::apiResource('sedes', SedeController::class)->only(['index', 'show', 'store', 'destroy', 'update']);
    Route::post('sede_update/{id}', [SedeController::class, 'updateSede']);
    Route::post('sedes/{id}', [SedeController::class, 'update']);
    Route::apiResource('areas', AreaController::class);
    Route::get('all_areas', [AreaController::class, 'getAllAreas']);


    Route::apiResource('cost_centers', CentroCostoController::class);
    Route::get('get_centros_operaciones', [CentroCostoController::class, 'getCentroOperaciones']);
    Route::post('store_centros_operaciones', [CentroCostoController::class, 'storeCentroOperacion']);
    Route::apiResource('configurations_nomina', ConfiguracionNominaController::class);
    Route::apiResource('configurations_incapacidades', ConfiguracionLicenciaController::class);
    Route::post('update_annual_increment_smlv/{id}', [ConfiguracionNominaController::class, 'updateAnnualIncrementSMLV']);
    Route::apiResource('historial_configurations_nomina', HistorialConfiguracionNominaController::class);
    Route::apiResource('nominas', NominaController::class);
    Route::get('nominas_by_contrato/{idContract}', [NominaController::class, 'getNominaByContract']);
    Route::get('get_my_nominas', [NominaController::class, 'getMyPayrolls']);
    Route::get('get_nominas_by_liquidacion/{idLiquidacion}', [NominaController::class, 'getNominaByLiquidacion']);
    Route::get('get_liquidacion_nomina', [NominaController::class, 'getLiquidacionesNomina']);
    Route::get('get_novedades_by_contrato/{id}', [NominaController::class, 'getNovedadesByContrato']);
    Route::get('get_novedades_aprobadas_by_contrato/{id}', [NominaController::class, 'getNovedadesAprobadasByContrato']);


    Route::post('solicitud_trabajador_horas_extra', [HoraExtraController::class, 'storeSolicitudHorasExtra']);
    Route::post('solicitud_trabajador_horas_extra_by_supervisor', [HoraExtraController::class, 'storeSolicitudHorasExtraSupervisor']);
    Route::post('calculate_nomina', [NominaController::class, 'calculateNomina']);
    Route::post('ejecutar_nomina_procedure', [NominaController::class, 'ejecutarNomina']);
    Route::post('ejecutar_nomina_procedure_individual', [NominaController::class, 'ejecutarNominaIndividual']);

    //ruta temporal
    Route::post('delete_all_liquidaciones', [NominaController::class, 'deleteAllLiquidaciones']);


    //horas extra
    Route::apiResource('horas_extras', HoraExtraController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::get('get_mis_horas_extras', [HoraExtraController::class, 'getMyOvertime']);
    Route::post('horas_extras/{id}', [HoraExtraController::class, 'update']);
    Route::get('horas_extra_trabajador', [HoraExtraController::class, 'getHorasExtraTrabajador']);
    Route::get('horas_extra_supervisor', [HoraExtraController::class, 'getHorasExtraSupervisor']);
    Route::get('comments_solicitud_hora_extra', [HoraExtraController::class, 'getCommentsSolicitudHorasExtra']);
    Route::post('store_comment_solicitud_hora_extra', [HoraExtraController::class, 'storeCommentHoraExtra']);
    Route::put('update_status_hora_extra_supervisor/{id}', [HoraExtraController::class, 'solicitudHoraExtraUpdateBySupervisor']);


    Route::apiResource('configuracion_horas_extra', ConfiguracionHorasExtraController::class);
    Route::apiResource('comisiones', ComisionController::class);

    Route::apiResources([
        'tarifas_arls' => TarifaArlController::class,
        'vacaciones'   => VacacionController::class,
        'solicitud_vacaciones' => SolicitudVacacionController::class,
        'observaciones_solicitud_vacac' => ObservacionSolicitudVacacionController::class,
        'tipos_incapacidades' => TipoIncapacidadController::class,
        'observacion_inc_personas' => ObservacionSolicitudIncLicPerController::class,
    ]);
    Route::post('solicitud_vacaciones_by_supervisor', [SolicitudVacacionController::class, 'getSolicitudesBySupervisor']);
    Route::post('create_solicitud_by_supervisor', [SolicitudVacacionController::class, 'createSolicitudVacacionBySupervisor']);
    Route::post('create_solicitud_vacaciones', action: [SolicitudVacacionController::class, 'createSolicitudVacacion']);
    Route::post('update_solicitud_by_worker/{id}', [SolicitudVacacionController::class, 'updatePeriodosByWorker']);
    Route::post('update_solicitud_by_supervisor/{id}', [SolicitudVacacionController::class, 'updateRequestVacation']);

    Route::get('contracts_actives_nominas', [ContratacionController::class, 'getContractsActivesNominas']);

    Route::apiResource('solicitud_inc_personas', SolicitudIncLicPersonaController::class);
    Route::post('extender_incapacidad_persona_by_trabajador', [SolicitudIncLicPersonaController::class, 'extederIncapcidadPersonaByTrabajador']);
    Route::post('extender_incapacidad_persona_by_supervisor', [SolicitudIncLicPersonaController::class, 'extederIncapcidadPersonaBySupervisor']);
    Route::post('calculate_days_restantes_cuidado_ninez', [SolicitudIncLicPersonaController::class, 'calculateDaysRestantesCuidadoNinez']);
    Route::post('solicitud_inc_personas/{id}', [SolicitudIncLicPersonaController::class, 'update']);
    Route::get('get_my_solicitudes_worker', [SolicitudIncLicPersonaController::class, 'getMySolicitudIncLicPersona']);
    Route::get('get_trazability_licencias/{id}', [SolicitudIncLicPersonaController::class, 'getTrazabilidadSolicitudIncLicPersona']);
    Route::post('solicitud_inc_by_supervisor', [SolicitudIncLicPersonaController::class, 'solicitudIncBySupervisor']);
    Route::put('update_status_by_supervisor/{id}', [SolicitudIncLicPersonaController::class, 'solicitudIncUpdateBySupervisor']);

    Route::get('/puntos-de-venta/{idPuntoVenta}/verificar-usuario', [CajaController::class, 'verificarUsuarioCaja']);
});



//gestion de puntos de venta
Route::resource('punto_de_ventas', PuntoVentaController::class);
Route::get('get_boxes_by_point_of_sale/{idPuntoVenta}', [PuntoVentaController::class, 'getCajasByPointSale']);
Route::get('caja-latest/{idPuntoDeVenta}', action: [PuntoVentaController::class, 'getLatestCajaByPuntoDeVenta']);


Route::middleware(['api'])->group(function () {
    Route::post('/caja-abrir/{idPuntoVenta}', [CajaController::class, 'abrirCaja']);
    Route::post('caja-cerrar/{idPuntoVenta}', [CajaController::class, 'cerrarCaja']);
    Route::get('/caja', [CajaController::class, 'obtenerDatosCajaActual']);
    Route::get('/puntos-de-venta/{idPuntoVenta}/verificar-usuario', [CajaController::class, 'verificarUsuarioCaja']);

    Route::get('transacciones_transferencias/{id}', [CajaController::class, 'getTransaccionesTranferencias']);
    Route::get('transacciones_efectivo/{id}', [CajaController::class, 'getTransaccionesEfectivo']);
    Route::get('transacciones_gastos/{id}', [CajaController::class, 'getTransaccionesGastos']);
    Route::post('store_gastos', [CajaController::class, 'storeGasto']);
    Route::get('get_transaccion_caja/{id}', [CajaController::class, 'getTransaccionCaja']);
});

Route::middleware(['api'])->group(function () {
    Route::apiResource('observaciones_viaje', ObservacionViajeController::class);
});



Route::post('punto_de_ventas_edit/{id}', [PuntoVentaController::class, 'updatePuntoVenta']);
Route::get('get_point_sales_by_sede/{idSede}', [PuntoVentaController::class, 'getPointSalesBySede']);
Route::get('get_point_sales_by_sede_tipe_shop/{idSede}', [PuntoVentaController::class, 'getPointSalesBySedeAndTypeShop']);


//afiliaciones-vehiculos
Route::apiResource('marcas', MarcasController::class);
Route::apiResource('modelos', ModelosController::class);
Route::apiResource('tipo_afiliaciones', TipoAfiliacionController::class);
Route::apiResource('tipo_vehiculos', TipoVehiculoController::class);
Route::apiResource('clase_vehiculos', ClaseVehiculoController::class);

Route::get('documents_by_proceso/{id}', [AfiliacionVehiculoController::class, 'getDocumentsByProceso']);
Route::get('documents_by_proceso_nombre/{nombre}', [AfiliacionVehiculoController::class, 'getDocumentsByNombreProceso']);
Route::get('pagos_by_proceso_nombre/{nombre}', [AfiliacionVehiculoController::class, 'getPagosByNombreProceso']);

Route::get('configuraciones_pago', [PagoController::class, 'getConfiguracionesPago']);
Route::post('store_configuracion_pago', [PagoController::class, 'storeConfiguracionPago']);
Route::put('update_configuracion_pago/{id}', [PagoController::class, 'updateConfiguracionPago']);
Route::delete('delete_configuracion_pago/{id}', [PagoController::class, 'destroyConfiguracionPago']);



//completar info persona natural afiliacion
Route::post('store_informacion_persona_natural', [AfiliacionVehiculoController::class, 'storeInformacionPersonaNatural']);
Route::get('get_info_persona_natural/{idPersonaNatural}', [AfiliacionVehiculoController::class, 'getInfoPersonaNatural']);
Route::post('store_referencias_personales', [AfiliacionVehiculoController::class, 'storeReferenciasPersonales']);
Route::delete('delete_referencia_personal/{id}', [AfiliacionVehiculoController::class, 'deleteReferenciaPersonal']);
Route::get('get_referencias_personales/{idPersonaNatural}', [AfiliacionVehiculoController::class, 'getReferenciasPersonales']);

//completar info persona juridica afiliacion
Route::get('get_info_persona_juridica/{idPersonaJuridica}', [AfiliacionVehiculoController::class, 'getInfoPersonaJuridica']);
Route::post('store_informacion_persona_juridica', [AfiliacionVehiculoController::class, 'storeInformacionJuridica']);

Route::group([
    'middleware' => 'api',
], function () {
    Route::apiResource('places', LugarController::class);
    Route::apiResource('rutas', RutaController::class);
    Route::post('create_sub_ruta', [RutaController::class, 'createSubRuta']);
    Route::put('update_sub_ruta/{id}', [RutaController::class, 'updateSubRuta']);
    Route::delete('delete_sub_ruta/{id}', [RutaController::class, 'deleteSubRuta']);
    Route::get('get_rutas_hijas/{id}', [RutaController::class, 'getRutasHijasByRutaPadre']);

    Route::apiResource('viajes', ViajeController::class);
    Route::patch('viajes/{id}/remove-vehicle', [ViajeController::class, 'removeVehicle']);
    Route::apiResource('estado_viaje', EstadoViajeController::class);

    Route::put('update_driver_to_trip/{id}', [ViajeController::class, 'updateDriver']);
    Route::put('update_vehicle_to_trip/{id}', [ViajeController::class, 'updateVehicle']);

    Route::apiResource('schedule_trip', AgendarViajeController::class);
    Route::post('save_trip_agenda', [AgendarViajeController::class, 'saveTripAgenda']);
    Route::apiResource('documentos_alcoholemia', DocumentoAlcoholemiaController::class)->except('update');
    Route::post('documentos_alcoholemia/{id}', [DocumentoAlcoholemiaController::class, 'update']);

    Route::apiResource('configuraciones_vehiculo', ConfiguracionVehiculoController::class);
    Route::apiResource('tickets', TicketController::class);


    Route::get('get_vehiculos_conductores', [AfiliacionVehiculoController::class, 'getVehiculosByDriver']);
    // Route::get('get_vehiculos_conductores', [AfiliacionVehiculoController::class, 'getVehiculosByDriver']);
    Route::get('get_vehiculos_by_owner', [AfiliacionVehiculoController::class, 'getVehiculosByOwner']);
});

Route::get('generate_ticket_pdf', [ReportController::class, 'generatePDFTicket']);
Route::post('generate_ticket_copia', [ReportController::class, 'generatePDFTicketByCC']);
Route::post('get_tickets_by_cc', [ReportController::class, 'getTicketsByCC']);

Route::get('generate_alcoholimetria_conductor', [ReportController::class, 'generateAlcoholimetriaConductor']);

Route::post('send_ticket_mail', [ReportController::class, 'sendTicketEmail']);
Route::get('generate_planilla_pdf', [ReportController::class, 'generatePDFPlanilla']);
Route::post('send_planilla_mail', [ReportController::class, 'sendPlanillaEmail']);

Route::post('store_afiliacion', [AfiliacionVehiculoController::class, 'storeAfiliacion']);
Route::post('store_afiliacion_propietarios/{idVehiculo}/{idAfiliacion}', [AfiliacionVehiculoController::class, 'storePropietario']);
Route::post('store_afiliacion_conductores/{idVehiculo}/{idAfiliacion}', [AfiliacionVehiculoController::class, 'storeConductor']);

Route::get('exist_afiliacion/{numero}', [AfiliacionVehiculoController::class, 'existAfiliacion']);
Route::get('exist_placa/{placa}', [AfiliacionVehiculoController::class, 'existPlaca']);

Route::get('get_afiliaciones', [AfiliacionVehiculoController::class, 'getAfiliaciones']);
Route::get('get_afiliaciones_pendientes', [AfiliacionVehiculoController::class, 'getAfiliacionesPendientes']);
Route::put('change_status_afiliacion/{id}', [AfiliacionVehiculoController::class, 'changeStatusAfiliacion']);
Route::get('get_vehiculos_by_id/{id}', [AfiliacionVehiculoController::class, 'getVehiculosByAfiliacion']);
Route::get('get_propietarios_by_id/{id}', [AfiliacionVehiculoController::class, 'getPropietariosByAfiliacion']);
Route::get('get_conductores_by_id/{id}', [AfiliacionVehiculoController::class, 'getConductoresByAfiliacion']);
Route::get('get_observaciones_by_id/{id}', [AfiliacionVehiculoController::class, 'getObservacionByAfiliacion']);
Route::get('get_historial_afiliacion_by_id/{id}', [AfiliacionVehiculoController::class, 'getHistorialByAfiliacion']);
Route::post('store_observacion_afiliacion', [AfiliacionVehiculoController::class, 'storeObservacionAfiliacion']);
Route::post('change_propietario_administrador', [AfiliacionVehiculoController::class, 'changePropietarioAdministrado']);
Route::get('get_all_drivers', [AfiliacionVehiculoController::class, 'getDrivers']);
Route::get('get_all_vehiculos', [AfiliacionVehiculoController::class, 'getAllVehiculos']);
Route::get('get_afiliaciones_propietario', [AfiliacionVehiculoController::class, 'getAfiliacionesPropietario']);
Route::post('change_status_afiliacion_pendiente', [AfiliacionVehiculoController::class, 'changeStatusVinculacionPendiente']);

Route::get('get_documents_by_vehiculo/{id}', [AfiliacionVehiculoController::class, 'getDocumentsVehiculos']);
Route::post('update_documento_vehiculo', [AfiliacionVehiculoController::class, 'updateDocumentoVehiculo']);

Route::get('get_documents_by_propietario/{id}', [AfiliacionVehiculoController::class, 'getDocumentsPropietario']);
Route::post('update_documento_propietario', [AfiliacionVehiculoController::class, 'updateDocumentoPropietario']);

Route::get('get_documents_by_conductor/{id}', [AfiliacionVehiculoController::class, 'getDocumentsConductor']);
Route::get('get_documents_alert_driver/{idConductor?}', [AfiliacionVehiculoController::class, 'getDocumentsAlert']);
Route::get('get_vehicle_documents_alert/{idVehiculo?}', [AfiliacionVehiculoController::class, 'getVehicleDocumentsAlert']);


Route::post('update_documento_conductor', [AfiliacionVehiculoController::class, 'updateDocumentoConductor']);


//cambios estado propietarios, vehiculos y conductores desde la afiliacion
Route::put('change_status_vehiculo/{id}', [AfiliacionVehiculoController::class, 'changeStatusVehiculo']);
Route::put('change_status_propietario/{id}', [AfiliacionVehiculoController::class, 'cambiarEstadoPropietario']);
Route::put('change_status_conductor/{id}', [AfiliacionVehiculoController::class, 'cambiarEstadoConductor']);
Route::post('store_vehiculo_afiliacion/{idAfiliacion}', [AfiliacionVehiculoController::class, 'storeVehiculoAfiliacion']);
Route::post('update_vehiculo_afiliacion/{id}', [AfiliacionVehiculoController::class, 'updateVehiculoAfiliacion']);
Route::get('contratos_vinculacion/{idAfiliacion}', [AfiliacionVehiculoController::class, 'getContratosVinculacion']);
Route::post('store_contrato_vinculacion', [AfiliacionVehiculoController::class, 'storeContratoVinculacion']);

Route::get('pdf_persona_natural/{id}/{idVehiculo}', [AfiliacionVehiculoController::class, 'generarFormularioPersonaNatural']);
Route::get('pdf_persona_juridica/{id}/{idVehiculo}', [AfiliacionVehiculoController::class, 'generarFormularioPersonaJuridica']);



Route::middleware(['api'])->group(function () {
    //gestion almacenes
    // Route::resource('almacenes', AlmacenController::class);
    // Route::get('get_productos_almacen/{id}', [AlmacenController::class, 'getProductosAlmacen']);
    // Route::post('send_productos_almacen', [AlmacenController::class, 'sendProductosAlmacen']);
    Route::resource('escenarios', EscenarioController::class);
    Route::post('escenarios_upate/{id}', [EscenarioController::class, 'updateEscenario']);
});



Route::middleware(['api'])->group(function () {
    Route::get('/gestion_agendas_escenario', [AgendaEscenarioController::class, 'index']);
    Route::post('/gestion_agendas_escenario', [AgendaEscenarioController::class, 'store']);
    Route::get('/gestion_agendas_escenario/{agenda}', [AgendaEscenarioController::class, 'show']);
    Route::put('/gestion_agendas_escenario/{agenda}', [AgendaEscenarioController::class, 'update']);
    Route::delete('/gestion_agendas_escenario/{agenda}', [AgendaEscenarioController::class, 'destroy']);
    Route::get('/gestion_agendas-trabajador_escenario', [AgendaEscenarioController::class, 'agendasByUser']);
    Route::post('/gestion_agenda-rapida-trabajador_escenario', [AgendaEscenarioController::class, 'crearAgendaRapida']);
    Route::post('/gestion_finalizar_escenario/{idAgenda}', [AgendaEscenarioController::class, 'terminarServicio']);
    Route::post('/gestion_update_escenario/{idAgenda}', [AgendaEscenarioController::class, 'updateAgendaEscenario']);
    Route::post('gestion_agendas_escenario/{idAgenda}/notificar-inicio', [AgendaEscenarioController::class, 'notificarInicioEventoEscenario']);
    //ruta finalizar si reserva se repite
    Route::post('/gestion_finalizar_serie_escenario/{idAgenda}', [AgendaEscenarioController::class, 'finalizarSerieReservas']);
    // NUEVA RUTA: Ruta para eliminar la serie completa por el ID de configuración de repetición
    Route::delete('/agendas/serie/{idConfiguracion}', [AgendaEscenarioController::class, 'destroySerie']);
    Route::get('/check_escenario_disponibilidad', [AgendaEscenarioController::class, 'checkDisponibilidad']);
});



Route::middleware(['api'])->group(function () {
    //gestion ventas
    Route::post('store_venta_contado', [VentaController::class, 'createTransaccionAndPagoContado']);
    Route::post('store_venta_credito', [VentaController::class, 'createTransaccionAndPagoCredito']);
    Route::get('get_productos_punto_venta/{id}', [VentaController::class, 'getProductosPuntosVenta']);
    Route::get('get_stock_minimo_punto_venta/{id}', [VentaController::class, 'getStockMinimoPuntoVenta']);
    Route::get('get_productos_punto_venta_menu/{id}', [VentaController::class, 'getProductosPuntosVentaMenu']);
    Route::get('validate_existence_producto/{id}', [VentaController::class, 'validateExistenceProducto']);

    Route::post('change_status_pendiente/{id}', [VentaController::class, 'changeStatusPendiente']);
    Route::post('change_status_disponible/{id}', [VentaController::class, 'changeStatusDisponible']);

    Route::middleware(['auth'])->group(function () {
        //gestion almacenes
        Route::resource('almacenes', AlmacenController::class);
        Route::get('get_productos_almacen/{id}', [AlmacenController::class, 'getProductosAlmacen']);
        Route::post('send_productos_almacen', [AlmacenController::class, 'sendProductosAlmacen']);
        Route::resource('escenarios', EscenarioController::class);
        Route::post('escenarios_upate/{id}', [EscenarioController::class, 'updateEscenario']);
    });

    //gestion ventas servicios
    Route::get('get_all_services', [VentaController::class, 'getAllServicios']);
    Route::get('get_services_asignados/{id}', [VentasServicioController::class, 'getServiciosAsignados']);
    Route::post('store_servicios', [VentasServicioController::class, 'storeServicios']);
    Route::post('asignar_responsable_servicio', [VentasServicioController::class, 'asignarResponsable']);
    Route::post('observacion_servicio', [VentasServicioController::class, 'observacionServicio']);
    Route::post('cancelar_servicio/{id}', [VentasServicioController::class, 'cancelarServicio']);
    Route::post('finalizar_servicio/{id}', [VentasServicioController::class, 'finalizarServicio']);
    Route::post('pago_servicio', [VentasServicioController::class, 'storePagoServicio']);

    Route::post('store_shoppingcart_service', [VentasServicioController::class, 'storeShoppingCartService']);
    Route::post('store_shoppingcart_producto', [VentasServicioController::class, 'storeShoppingCartProducto']);

    Route::post('delete_item_shoppingcart_service', [VentasServicioController::class, 'deleteShoppingCartService']);
    Route::get('get_shoppingcart_service_pos', [VentasServicioController::class, 'getShoppingCartService']);
    Route::get('get_shoppingcart_productos_pos', [VentasServicioController::class, 'getShoppingCartProductsPos']);
    Route::get('count_shoppingcart_productos_pos', [VentasServicioController::class, 'countShoppingCartProductsPos']);
    Route::get('get_ahorros_terceros', [VentaController::class, 'getAhorrosTerceros']);

    Route::put('store_iva_shopping/{id}', [VentasServicioController::class, 'storeIvaShopping']);

    Route::get('get_articulo_servicio/{id}', [VentasServicioController::class, 'getArticuloService']);
    Route::get('get_multimedia_articulo_servicio/{id}', [VentasServicioController::class, 'getMultimediaArticuloServicio']);
    Route::delete('delete_multimedia_articulo/{id}', [VentasServicioController::class, 'deleteMultimediaArticuloServicio']);
    Route::post('store_multimedia_articulo_servicio', [VentasServicioController::class, 'storeMultimediaArticuloServicio']);
});
Route::resource('tipo_identificaciones', controller: IdentificationTypeController::class);



//responsable servicios
Route::resource('responsable_servicio', ResponsableServicioController::class);
//responsables l
Route::get('responsables', [ResponsableServicioController::class, 'index']);



//servicios productos
Route::resource('servicios', ServicioController::class);
Route::get('service_by_company', [ServicioController::class, 'getServicesByCompany']);
Route::post('asignar_servicio_escenario', [ServicioController::class, 'asignacionEscenarioServicio']);
Route::apiResource('category_services', CategoriaServicioController::class);



//tipo servicio
Route::resource('tipo_servicios', TipoServicioController::class);
Route::get('get_type_request_services', [TipoServicioController::class, 'getTypeServicesRequest']);
Route::get('clase_servicios', [TipoServicioController::class, 'getClaseServices']);
Route::get('tipo_services_by_id/{id}', [TipoServicioController::class, 'getTipoServicios']);
Route::post('store_clase_servicio', [TipoServicioController::class, 'storeClaseServicio']);



//reportes
Route::middleware(['api'])->group(function () {
    // Route::get('count_all_products', [ReporteController::class, 'getProductosInventario']);
    // Route::get('total_all_ventas', [ReporteController::class, 'getValoresVentasTotales']);
    // Route::get('total_all_ventas_dia', [ReporteController::class, 'getVentasPorDia']);
    // Route::get('total_customers', [ReporteController::class, 'getCustomers']);

    // Route::get('reporte_servicios/{id}', [ReportesController::class, 'reportesServiciosResponsables']);
    // Route::get('reporte_servicios_persona', [ReporteController::class, 'reportesServiciosByResponsable']);
    // Route::get('reporte_servicios_all_responsables', [ReporteController::class, 'reportesServiciosTodosResponsables']);

    // Route::post('store_pago_responsables', [ReporteController::class, 'storePagoResponsable']);

    // Route::get('reporte_flujo_ventas', [ReporteController::class, 'reporteFlujoVentas']);
    // Route::get('reporte_productos_mas_vendidos', [ReporteController::class, 'productsMoreSell']);
    // Route::get('reporte_general_company', [ReporteController::class, 'reporteGeneralByCompany']);
    // Route::get('detalle_transaccion/{id}', [ReporteController::class, 'getDetalleTransaccion']);
});



Route::middleware(['api'])->group(function () {
    //configuracion servicios
    Route::resource('responsable_servicios', ResponsableServicioController::class);
    // Route::resource('tipo_articulos', TipoArticuloController::class);

    //configuracion productos
    // Route::get('products_by_tipo_producto', [VentasServicioController::class, 'getProductosByTipoProducto']);
    // Route::post('update_valor_venta_producto', [VentasServicioController::class, 'updateValorVentaProducto']);

    // Route::get('products_catalogo_menu', [VentaController::class, 'getProductosMenu']);
    // Route::post('store_producto_menu', [VentaController::class, 'storeProductoMenu']);
    // Route::post('delete_producto_menu/{id}', [VentaController::class, 'deleteProductoMenu']);
    // Route::post('actualizar_producto_menu/{id}', [VentaController::class, 'updateProductoMenu']);
});



//ruta para horas disponibles de un prestador
// Route::get('/agenda/horas-ocupadas/{idResponsable}/{fecha}', [AgendaController::class, 'horasOcupadas']);

Route::get('get_services_by_escenario/{id}', [ServicioController::class, 'ServiciosByEscenario']);

// Route::get('get_entidades_bancarias', [ComprasController::class, 'getEnumBancos']);



Route::get('get_prestadores_company/{id}', [ResponsableServicioController::class, 'getPrestadoresCompany']);
Route::get('get_escenarios_by_service/{id}', [ServicioController::class, 'getEscenariosByServicio']);


//shopping cart functions web
// Route::post('store_shopping_cart_company', [ComprasWebController::class, 'storeShoppingCartCompany']);
// Route::get('get_shopping_cart_company', [ComprasWebController::class, 'getShoppingCartCompany']);
Route::post('decrease_quantity/{id}', [ComprasWebController::class, 'decreaseQuantity']);
Route::post('increase_quantity/{id}', [ComprasWebController::class, 'increaseQuantity']);
Route::delete('delete_shopping_cart/{id}', [ComprasWebController::class, 'destroyShoppingCart']);



//Facturacion electronica
Route::post('login_factus', [AuthFactusController::class, 'getTokenFactus']);
Route::post('refesh_token_factus', [AuthFactusController::class, 'refreshToken']);

// Facturación Electrónica
Route::get('get_facturas_electronicas', [FacturacionElectronicaController::class, 'getFacturasElectronicas'])->middleware('api');
Route::get('facturas_electronicas/stats', [FacturacionElectronicaController::class, 'getFacturasStats'])->middleware('api');
Route::get('facturas_electronicas/{id}', [FacturacionElectronicaController::class, 'getFacturaDetails'])->middleware('api');
Route::post('facturas_electronicas/retry', [FacturacionElectronicaController::class, 'retryFailedInvoices'])->middleware('api');


//Gestion entidades seguridad social
Route::apiResource('entidades_seguridad_social', EntidadesSeguridadSocialController::class);
Route::get('entidades/eps', [EntidadesSeguridadSocialController::class, 'getEPS']);
Route::get('entidades/pensiones', [EntidadesSeguridadSocialController::class, 'getPensiones']);
Route::get('entidades/arl', [EntidadesSeguridadSocialController::class, 'getARL']);
Route::get('entidades/caja_compensacion', [EntidadesSeguridadSocialController::class, 'getCajaCompensacion']);
Route::get('entidades/cesantias', [EntidadesSeguridadSocialController::class, 'getCesantias']);


//historis and music
Route::get('/deezer/search', [MultimediaHistoriasController::class, 'searchTrack']);
Route::get('/deezer/search/{id}', [MultimediaHistoriasController::class, 'getTrack']);





Route::group([
    'middleware' => 'api',
], function () {

    Route::get('multimedia_by_company', [MultimediaHistoriasController::class, 'getGruposMultimedia']);

    Route::post('store_grupo_multimedia', [MultimediaHistoriasController::class, 'storeMultimediaGrupo']);

    Route::post('update_grupo_multimedia/{id}', [MultimediaHistoriasController::class, 'updateMultimediaGrupo']);

    Route::delete('delete_grupo_multimedia/{id}', [MultimediaHistoriasController::class, 'destroyGrupoMultimedia']);
    Route::delete('delete_multimedia_by_user/{id}', [MultimediaHistoriasController::class, 'destroyMultimediaUser']);

    Route::get('get_stories', [MultimediaHistoriasController::class, 'getStories']);
    Route::get('get_stories_by_user', [MultimediaHistoriasController::class, 'getStoriesByUser']);

    Route::get('get_stories_by_company/{id}', [MultimediaHistoriasController::class, 'getStoriesByCompany']);
});
//tipo conceptos
Route::get('get_tipo_conceptos', [OtrasDeduccionesController::class, 'getTipoConceptos']);
Route::post('store_tipo_conceptos', [OtrasDeduccionesController::class, 'storeTipoConcepto']);

Route::get('get_deducciones', [OtrasDeduccionesController::class, 'getDeducciones']);
Route::get('get_deducciones_persona', [OtrasDeduccionesController::class, 'getDeduccionesUserContrato']);


Route::post('store_deducciones', [OtrasDeduccionesController::class, 'storeDeducciones']);

Route::put('update_status_deducciones/{id}', [OtrasDeduccionesController::class, 'updateStatusDeduccion']);


Route::get('get_report_superintendencia', [ReporteSuperIntendenciaController::class, 'getReportSuperIntendencia']);
Route::delete('delete_report_superintendencia', [ReporteSuperIntendenciaController::class, 'deleteReportSuperIntendencia']);
Route::post('update_report_superintendencia/{id}', [ReporteSuperIntendenciaController::class, 'updateReportSuperIntendencia']);

Route::post('store_report_superintendencia', [ReporteSuperIntendenciaController::class, 'storeReportSuperIntendencia']);

Route::get('get_persona_auxiliar/{identificacion}', [ReporteSuperIntendenciaController::class, 'getPersonaAux']);
Route::get('get_vehiculo_auxiliar/{placa}', [ReporteSuperIntendenciaController::class, 'getVehiculoAux']);
Route::post('store_persona_auxiliar', [ReporteSuperIntendenciaController::class, 'storePersonaAux']);
Route::post('store_vehiculo_auxiliar', [ReporteSuperIntendenciaController::class, 'storeVehiculoAux']);


Route::get('get_bonificaciones', [BonificacionController::class, 'getBonificaciones']);
Route::post('store_bonificacion', [BonificacionController::class, 'storeBonificacion']);
Route::post('store_bonificacion', [BonificacionController::class, 'storeBonificacion']);


Route::get('get_reemplazos', [ReemplazoController::class, 'getReemplazos']);
Route::get('get_reemplazos_finalizados', [ReemplazoController::class, 'getgetReemplazosFinalizadosReemplazos']);
Route::post('store_reemplazo', [ReemplazoController::class, 'storeReemplazo']);
Route::put('update_reemplazo/{id}', [ReemplazoController::class, 'updateReemplazo']);
Route::delete('delete_reemplazo/{id}', [ReemplazoController::class, 'destroyReemplazo']);


//turnos
Route::apiResource('turnos', TurnoController::class);
Route::get('turno_actual_conductor', [TurnoController::class, 'turnoActualConductor']);
Route::post('turnos/{id}/finalizar', [TurnoController::class, 'finalizarTurno']);
//planillas del usuario
Route::get('planillas_usuario/{idCaja}', [ReportController::class, 'getPlanillasByUser']);
Route::get('planillas_despachadas_usuario/{idCaja}', [ReportController::class, 'getPlanillasDespachadasUser']);


/*
|--------------------------------------------------------------------------
| API Routes - Detalles de Revisión
|--------------------------------------------------------------------------
*/

Route::resource('detalle_revision', DetalleRevisionController::class)->except(['create', 'edit'])->middleware('api');

/*
|--------------------------------------------------------------------------
| API Routes - Asignación Detalle Revisión Vehículo
|--------------------------------------------------------------------------
*/

Route::resource('asignacion_revision_vehiculo', AsignacionDetalleRevisionVehiculoController::class)->except(['create', 'edit'])->middleware('api');
Route::get('/vehiculos/{idVehiculo}/ultima_revision', [AsignacionDetalleRevisionVehiculoController::class, 'ultimaRevisionCompleta']);
Route::get('/vehiculos/{idVehiculo}/revisiones_recientes', [AsignacionDetalleRevisionVehiculoController::class, 'revisionesRecientes']);

Route::get('vehiculos_con_rechazos', [AsignacionDetalleRevisionVehiculoController::class, 'vehiculosConRechazos']);

Route::apiResource('configuracion_mantenimiento', ConfiguracionMantenimientoController::class)->middleware('api');
Route::apiResource('descuentos_planilla', DescuentoPlanillaController::class)->middleware('api');
Route::apiResource('asignacion_descuentos_viaje', AsignacionDescuentoPlanillaController::class)->middleware('api');

Route::prefix('asignacion_descuentos')->group(function () {
    Route::delete('/viaje/{idViaje}', [AsignacionDescuentoPlanillaController::class, 'destroyByViaje']);
    Route::get('/resumen/viaje/{idViaje}', [AsignacionDescuentoPlanillaController::class, 'resumenViaje']);
})->middleware('api');


Route::get('/test-pdf-observaciones', [ReporteObservacionesRevisionController::class, 'generatePDFObservaciones'])->middleware('api');


Route::post('store_registro_permiso_menor', [AutorizacionController::class, 'storeRegistroPermisoMenor']);


Route::resource('reservas', ReservaViajeController::class)->middleware('api');
Route::post('reservas_update/{idReserva}', [ReservaViajeController::class, 'updateEstadoReservaRedimido'])->middleware('api');
Route::get('reservas/codigo/{codigo}', [ReservaViajeController::class, 'buscarPorCodigo'])
    ->name('reservas.buscar-codigo');
Route::get('reservas/{reservaViaje}/qr', [ReservaViajeController::class, 'generarQR'])->name('reservas.generar-qr');
Route::patch('reservas/{reservaViaje}/estado', [ReservaViajeController::class, 'cambiarEstado'])->name('reservas.cambiar-estado');

Route::get('reservas/{idReserva}/pdf', [ReservaViajeController::class, 'generatePDFReserva'])->name('reservas.pdf');

Route::get('get_viajes_for_autorizacion', [AutorizacionController::class, 'getViajesForAutorizacion']);

Route::post('store_registro_permiso_menor', [AutorizacionController::class, 'storeRegistroPermisoMenor']);
Route::get('get_viajes_for_autorizacion', [AutorizacionController::class, 'getViajesForAutorizacion']);

Route::apiResource('grupos_nomina', GrupoNominaController::class);

// configuaracion productos 

Route::get('products_by_tipo_producto', [ConfiguracionProductosController::class, 'getProductosByTipoProducto']);
Route::post('update_valor_venta_producto', [ConfiguracionProductosController::class, 'updateValorVentaProducto']);
Route::put('/producto/editar-campos/{id}', [ConfiguracionProductosController::class, 'editarCamposProducto']);
Route::post('store_producto_individual', [ConfiguracionProductosController::class, 'storeProductoIndividual']);
Route::get('get_historial_precios/{idProducto}', [ConfiguracionProductosController::class, 'getHistorialPrecios']);
Route::get('productos_con_historial', [ConfiguracionProductosController::class, 'productosConHistorial']);

//pedidos
Route::get('get_pedidos', [GestionPedidosController::class, 'getPedidos']);
Route::put('update_pedido/{id}', [GestionPedidosController::class, 'updatePedido']);
Route::get('get_pedidos_pendientes', [GestionPedidosController::class, 'getPedidosPendientes']);

//medidas
Route::resource('medidas', MedidasProController::class);
Route::get('medida/{id}', [MedidasProController::class, 'getMedidas']);

//categorias
Route::resource('categorias', CategoriasProController::class);
Route::get('get_all_categories', [CategoriasProController::class, 'getAllCategories']);
Route::get('get_all_categories_company/{id}', [CategoriasProController::class, 'getAllCategoriesWebPage']);

//ruta para traer asociados administradores activos
Route::get('get_asociados_admin', [PolizasController::class, 'getAsociadosAdmin']);
Route::post('store_cuenta_cobrar_poliza', [PolizasController::class, 'storeCuentaCobrarPagoPoliza']);

//rutas SCHOOL para gestión de programas académicos
Route::get('programas_recursos_crear', [PensumController::class, 'getMetadata']);
Route::post('programas_guardar', [PensumController::class, 'store']);
Route::get('programas', [PensumController::class, 'index']);
Route::put('programas_actualizar/{id}', [PensumController::class, 'update']);
Route::delete('programas_eliminar/{id}', [PensumController::class, 'destroy']);
Route::get('asignacion_detalle/{id}', [PensumController::class, 'getInformacionApertura']);
Route::put('asignacion_detalle_update/{id}', [PensumController::class, 'updateInformacionApertura']);
Route::get('asignacion_detalle_completo/{id}', [NivelesProgramaController::class, 'getDetalleAsignacion']);

//ruta de Periodos 
Route::post('/periodos', [PeriodosController::class, 'store']);
Route::get('/periodos', [PeriodosController::class, 'index']);
Route::put('/periodos/{id}', [PeriodosController::class, 'update']);
Route::delete('/periodos/{id}', [PeriodosController::class, 'destroy']);


//rutas SCHOOL SENA para gestión de regionales
Route::post('regional', [RegionalController::class, 'store']);
Route::get('regional', [RegionalController::class, 'index']);
Route::get('regional/{id}', [RegionalController::class, 'show']);
Route::patch('regional/{id}', [RegionalController::class, 'update']);

//rutas SHOOL SENA para gestión de Centros de Formación:
Route::post('centrosFormacion', [CentrosFormacionController::class, 'store']);
Route::get('centrosFormacion', [CentrosFormacionController::class, 'index']);

//rutas de Jornadas
Route::post('jornadas/crear_jornada_materias', [JornadaController::class, 'crearJornadaMaterias']);
Route::get('jornadas/agrupadas', [JornadaController::class, 'getJornadasMaterias']);
Route::delete('jornadas/eliminar', [JornadaController::class, 'eliminarJornadaMaterias']);
Route::put('jornadas/actualizar', [JornadaController::class, 'actualizarJornadaMaterias']);
Route::put('jornadas/cambiar-estado', [JornadaController::class, 'cambiarEstadoJornada']);

//rutas sedes institucional
Route::get('sedes-institucionales', [SedeInstitucionalController::class, 'index']);
Route::post('sedes-institucionales', [SedeInstitucionalController::class, 'store']);
Route::put('sedes-institucionales/{id}', [SedeInstitucionalController::class, 'update']);
Route::delete('sedes-institucionales/{id}', [SedeInstitucionalController::class, 'destroy']);
Route::get('sedes-institucionales/{id}', [SedeInstitucionalController::class, 'show']);

// Rutas de Infraestructura
Route::get('/infraestructuras/tipos', [InfraestructuraController::class, 'tipos']); 
Route::get('sedes/{idSede}/infraestructuras', [InfraestructuraController::class, 'index']);
Route::post('infraestructuras', [InfraestructuraController::class, 'store']);
Route::get('infraestructuras/{id}', [InfraestructuraController::class, 'show']);
Route::put('infraestructuras/{id}', [InfraestructuraController::class, 'update']);
Route::delete('infraestructuras/{id}', [InfraestructuraController::class, 'destroy']);
