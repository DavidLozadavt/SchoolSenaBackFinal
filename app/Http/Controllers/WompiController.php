<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaPlanMensaje;
use App\Models\MensajesPlan;
use App\Models\SolicitudPlanMensaje;
use App\Models\WompiTransaccion;
use App\Services\Mensajes\AuditoriaPlanesService;
use App\Services\Mensajes\NotificacionesPlanesService;
use App\Services\Pagos\WompiService;
use App\Util\KeyUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Bancolombia\Wompi;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class WompiController extends Controller
{

  // KEYS TEST

  /**
   * $publicKeyTest => Key public of wompi (TEST)
   *
   * @var string
   */
  private $publicKeyTest;

  /**
   * $privateKeyTest => Key private of wompi (TEST)
   *
   * @var string
   */
  private $privateKeyTest;

  /**
   * $testEvents => Key of events (TEST)
   *
   * @var string
   */
  private $testEvents;

  /**
   * $testIntegrity => Key integrity of wompi (TEST)
   *
   * @var string
   */
  private $testIntegrity;

  // KEYS PRODUCTION

  /**
   * $publicKeyProd => Key public of wompi (PRODUCTION)
   *
   * @var string
   */
  private $publicKeyProd;

  /**
   * $privateKeyProd => Key private of wompi (PRODUCTION)
   *
   * @var string
   */
  private $privateKeyProd;

  /**
   * $prodEvents => Key of events (PRODUCTION)
   *
   * @var string
   */
  private $prodEvents;

  /**
   * $prodIntegrity => Key integrity of wompi (PRODUCTION)
   *
   * @var string
   */
  private $prodIntegrity;

  public function __construct()
  {


    // $this->publicKeyProd = "pub_prod_p1OMQLQqQEH48CoOs4bRNut9UCjLSzYp";
    // $this->privateKeyProd = "prv_prod_5gIXRCDVJSY3kWDafhODxGUuHeWDWvVb";
    // $this->prodEvents = "prod_events_qMbhHDzAuvKIlK4M0XV9QGnAycmBBAr4";
    // $this->prodIntegrity = "prod_integrity_1ovgzCilREBhqZkiTPEeZlh3KId0cwzg";


    $this->publicKeyTest  = "pub_test_rEa0dhl2NZNwXMcadIVLu3WKh3R4hVb1";
    $this->privateKeyTest = "prv_test_J9JkrcvGMzWoiKzUbvGxtdJ8N62H0KJC";
    $this->testEvents     = "test_events_Opkn8XPIjo6FsicRPaRE3VEutcFG3yAc";
    $this->testIntegrity  = "test_integrity_wbIgI27vV8lqn4dEKiCvTtjHPhGZo5Xv";


    Wompi::initialize([
      'public_key'  => $this->publicKeyTest,
      'private_key' => $this->privateKeyTest,
    ]);
  }

  /**
   * Initialize credentials by company
   *
   * @return void
   */


  /**
   * Get wompi credentials for my company only in the backend to make transactions by students
   *
   * @return JsonResponse
   */


  /**
   * Get keys of wompi
   *
   * @return JsonResponse
   */
  public function getTokens(): JsonResponse
  {
    $tokens = Wompi::getTokens();
    return response()->json($tokens, 200);
  }

  /**
   * Get link of accept terms and conditions wompi
   *
   * @return JsonResponse
   */
  public function getPermalink(): JsonResponse
  {
    $allDataWithAcceptanceToken = $this->getAllDataWithAcceptanceToken();
    return response()->json($allDataWithAcceptanceToken->original->data->presigned_acceptance->permalink);
  }

  /**
   * Get only acceptance token wompi
   *
   * @return JsonResponse
   */
  public function getOnlyAcceptanceToken(): JsonResponse
  {
    $allDataWithAcceptanceToken = $this->getAllDataWithAcceptanceToken();
    return response()->json($allDataWithAcceptanceToken->original->data->presigned_acceptance->acceptance_token);
  }

  /**
   * Get all data with acceptance token
   *
   * @return JsonResponse
   */
  public function getAllDataWithAcceptanceToken(): JsonResponse
  {
    $acceptanceToken = Wompi::acceptance_token();
    return response()->json($acceptanceToken, 200);
  }

  /**
   * Get institutions of PSE
   *
   * @return JsonResponse
   */
  public function getFinancialInstitutions(): JsonResponse
  {
    $financialInstitutions = Wompi::financial_institutions();
    return response()->json($financialInstitutions, 200);
  }

  /**
   * Find transaction by id (Long polling)
   *
   * @param mixed $idTransaction
   * @return JsonResponse
   */
  public function findTransactionById(mixed $idTransaction): JsonResponse
  {
    $url = Wompi::transaction_find_by_id($idTransaction);
    return response()->json($url);
  }

  /**
   * Pay with PSE
   *
   * @param Request $request
   * @return void
   */
  public function makePSEPayment(Request $request): JsonResponse
  {
    $data = $request->all();

    $acceptanceTokenData = $this->getOnlyAcceptanceToken();
    $acceptanceToken = $acceptanceTokenData->original; // Hay que acceder a original para el token

    // Tipo de persona (natural o jurídica)
    $tipo_persona = $data['tipoPersona']; // 0 para natural, 1 para jurídica

    // Tipo de documento (CC o NIT)
    // Obtener el tipo de documento
    switch ($data['tipoDocumento']) {
      case "0":
        $tipo_documento = "CC";
        break;
      case "1":
        $tipo_documento = "CE";
        break;
      case "2":
        $tipo_documento = "NIT";
        break;
      default:
        $tipo_documento = $data['tipoDocumento'];
        break;
    }

    // Número de documento
    $numero_documento = $data['numeroDocumento'];

    // Código de la institución financiera
    $codigo_institucion = $data['codigoInstitucion'];

    // Descripción del pago
    $payment_description = $data['paymentDescription'];

    $amountInCents = intval($data['amountInCents'] . "00");

    $randomReference = mt_rand(1000000000, 9999999999);

    $requestData = new Request([
      'reference'     => strtr($randomReference, 0, 10),
      'amountInCents' => $amountInCents,
      'currency'      => "COP"
    ]);

    $hash = $this->getCryptoGragraphicHash($requestData);

    // Create transaction with PSE
    $pseTransaction = Wompi::pse(
      $acceptanceToken,
      $tipo_persona,
      $tipo_documento,
      $numero_documento,
      $codigo_institucion,
      $payment_description,
      [
        "amount_in_cents" => $amountInCents,
        "currency"        => "COP",
        "customer_email"  => $data['email'],
        "reference"       => strtr($randomReference, '0', '10'),
        "created_at"      => now(),
        "redirect_url"    => $this->validateUrl(),
        "signature"       => $hash->original,
        "customer_data" => [
          "phone_number" => "57" . $data['numeroCelular'],
          "full_name"    => $data['nombreCompleto'],
          "legalId"      => $data['numeroDocumento'],
          "legalIdType"  => $tipo_documento,
          "expiration-time" => $this->expirationTimeTransaction()
        ],
      ],
    );

    return response()->json($pseTransaction);
  }

  /**
   * Validate url execute project
   *
   * @return string
   */
  private function validateUrl(): string
  {
    $appUrl = rtrim(env('APP_URL'), '/'); // Elimina la barra final si existe

    return ($appUrl === 'http://localhost:8000')
      ? 'http://localhost:4200/gestion-matricula-estudiante'
      : 'https://pre-school-plataform.virtualt.org/gestion-matricula-estudiante';
  }

  /**
   * is url local or pre-production or production
   *
   * @return boolean
   */
  private function isLocalUrl(): bool
  {
    $url = $this->validateUrl();

    // Verifica si la URL contiene 'localhost'
    if (strpos($url, 'localhost') !== false) {
      return true;
    }

    return false;
  }

  /**
   * Get hash with key integrity
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function getCryptoGragraphicHash(Request $request): JsonResponse
  {
    $data = $request->all();

    $credentialsFromCache = $this->getCredentialsWompiFromCache();

    if (isset($data['reference']) && isset($data['amountInCents']) && isset($data['currency'])) {

      $concatenatedData = $data['reference'] .
        $data['amountInCents'] .
        $data['currency'] .
        $this->prodIntegrity;

      $hashValue = hash("sha256", $concatenatedData);
      return response()->json($hashValue);
    } else {
      return response()->json("Error: Faltan datos en la solicitud.", 422);
    }
  }

  /**
   * Get time of expiration of transaction
   *
   * @return void
   */
  private function expirationTimeTransaction()
  {
    // Obtiene la fecha y hora actual
    $now = Carbon::now('UTC');

    // Calcula la fecha y hora de expiración que es 30 minutos después del momento actual
    $expirationDateTime = $now->copy()->addMinutes(30);

    // Calcula el tiempo restante para la expiración del inicio del pago
    $tiempoRestante = $now->diffForHumans($expirationDateTime, true);

    return $tiempoRestante;
  }

  /**
   * Get credentials by company from cache
   *
   * @return array|null
   */

  // ===========================================================================
  // COMPRA DE PLANES DE MENSAJES (Checkout Web de Wompi)
  //
  // Métodos migrados desde el antiguo WompiPagoController para que este sea el
  // ÚNICO controlador del dominio Wompi. No alteran el constructor, el SDK
  // Bancolombia\Wompi ni el flujo PSE de arriba: resuelven sus dependencias con
  // app() y usan exclusivamente WompiService.
  // ===========================================================================

  /**
   * Resumen previo al checkout: plan, mensajes, valor, descripción, usuario y empresa.
   */
  public function resumenPlan($planId): JsonResponse
  {
    try {
      $wompiService = app(WompiService::class);
      $plan = MensajesPlan::findOrFail($planId);

      if (!$plan->activo) {
        return response()->json(['error' => 'El plan seleccionado no está activo.'], 422);
      }

      $usuario   = auth()->user();
      $companyId = $this->resolverCompanyIdPlan((int) $usuario->id);
      $empresa   = $companyId ? DB::table('empresa')->where('id', $companyId)->value('razonSocial') : null;

      $metodos = $wompiService->metodosDisponibles();

      return response()->json([
        'plan' => [
          'id'               => $plan->id,
          'nombre'           => $plan->nombre,
          'cantidadMensajes' => $plan->cantidadMensajes,
          'precio'           => $plan->precio,
          'descripcion'      => $plan->descripcion,
        ],
        'usuario' => [
          'id'     => $usuario->id,
          'nombre' => $usuario->name ?: $usuario->email,
          'email'  => $usuario->email,
        ],
        'empresa' => [
          'id'     => $companyId,
          'nombre' => $empresa,
        ],
        'importes'         => $this->desglosarImportesPlan((float) $plan->precio),
        'moneda'           => $wompiService->moneda(),
        'wompiConfigurado' => $wompiService->estaConfigurado(),
        'metodosPago'      => $metodos['ok'] ? $metodos['metodos'] : [],
      ], 200);
    } catch (\Exception $e) {
      return response()->json(['error' => 'Error al obtener el resumen de compra: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Crea la transacción local y devuelve los datos firmados del Checkout Wompi.
   * El frontend solo envía `planId`: importe, moneda y cantidad salen de la BD.
   */
  public function checkoutPlan(Request $request): JsonResponse
  {
    try {
      $wompiService = app(WompiService::class);

      $data = $request->validate([
        'planId' => ['required', 'integer', 'exists:mensajesPlanes,id'],
      ]);

      if (!$wompiService->estaConfigurado()) {
        return response()->json([
          'error' => 'La pasarela de pagos Wompi no está configurada en este backend.',
        ], 422);
      }

      $plan = MensajesPlan::findOrFail($data['planId']);

      if (!$plan->activo) {
        return response()->json(['error' => 'El plan seleccionado no está activo.'], 422);
      }

      $usuario       = auth()->user();
      $amountInCents = (int) round(((float) $plan->precio) * 100);

      if ($amountInCents <= 0) {
        return response()->json(['error' => 'El plan no tiene un precio válido para cobrar.'], 422);
      }

      $reference = $wompiService->generarReferencia((int) $usuario->id, (int) $plan->id);

      $transaccion = WompiTransaccion::create([
        'reference'           => $reference,
        'userId'              => $usuario->id,
        'companyId'           => $this->resolverCompanyIdPlan((int) $usuario->id),
        'planId'              => $plan->id,
        'amountInCents'       => $amountInCents,
        'amount'              => $plan->precio,
        'currency'            => $wompiService->moneda(),
        'status'              => WompiTransaccion::PENDING,
        'customerEmail'       => $usuario->email,
        'origenActualizacion' => 'CHECKOUT',
      ]);

      return response()->json([
        'message'     => 'Transacción creada. Continúe en el checkout de Wompi.',
        'transaccion' => $transaccion,
        'checkout'    => $wompiService->datosCheckout($reference, $amountInCents, $usuario->email),
      ], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json(['error' => $e->validator->errors()->first()], 400);
    } catch (\Exception $e) {
      Log::error('Error al crear la transacción de Wompi: ' . $e->getMessage());

      return response()->json(['error' => 'Error al crear la transacción: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Consulta el estado real en Wompi al regresar del checkout y lo sincroniza.
   */
  public function confirmarPlan(Request $request): JsonResponse
  {
    try {
      $wompiService  = app(WompiService::class);
      $transactionId = $request->get('id');
      $reference     = $request->get('reference');

      if (!$transactionId && !$reference) {
        return response()->json(['error' => 'Debe indicar el id de la transacción o la referencia.'], 400);
      }

      $resultado = $transactionId
        ? $wompiService->consultarTransaccion($transactionId)
        : $wompiService->consultarPorReferencia($reference);

      if (!$resultado['ok']) {
        return response()->json(['error' => $resultado['error']], 422);
      }

      $datos       = $resultado['data'];
      $transaccion = WompiTransaccion::where('reference', $datos['reference'] ?? $reference)->first();

      if (!$transaccion) {
        return response()->json(['error' => 'La transacción no existe en el sistema.'], 404);
      }

      if ((int) $transaccion->userId !== (int) auth()->id()) {
        return response()->json(['error' => 'No tiene acceso a esta transacción.'], 403);
      }

      $this->sincronizarTransaccionWompi($transaccion, $datos, 'CONSULTA');

      $transaccion->refresh();

      return response()->json([
        'message'     => 'Estado de la transacción actualizado.',
        'transaccion' => $transaccion,
        'solicitud'   => $transaccion->solicitudId
          ? SolicitudPlanMensaje::find($transaccion->solicitudId)
          : null,
      ], 200);
    } catch (\Exception $e) {
      Log::error('Error al confirmar el pago de Wompi: ' . $e->getMessage());

      return response()->json(['error' => 'Error al confirmar el pago: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Transacciones del usuario autenticado.
   */
  public function misTransaccionesPlan(): JsonResponse
  {
    try {
      return response()->json(
        WompiTransaccion::where('userId', auth()->id())->orderByDesc('id')->get(),
        200
      );
    } catch (\Exception $e) {
      return response()->json(['error' => 'Error al obtener las transacciones: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Detalle completo de una transacción (Administrador VT).
   */
  public function detalleTransaccionPlan($id): JsonResponse
  {
    try {
      return response()->json(WompiTransaccion::findOrFail($id), 200);
    } catch (\Exception $e) {
      return response()->json(['error' => 'Transacción no encontrada.'], 404);
    }
  }

  /**
   * Webhook oficial de Wompi (ruta pública). Valida la firma del evento.
   */
  public function webhookPlan(Request $request): JsonResponse
  {
    try {
      $wompiService = app(WompiService::class);
      $payload      = $request->all();

      if (!$wompiService->verificarFirmaEvento($payload)) {
        Log::warning('Webhook Wompi: firma inválida.', ['evento' => $payload['event'] ?? null]);

        return response()->json(['error' => 'Firma inválida.'], 401);
      }

      $datos     = $payload['data']['transaction'] ?? [];
      $reference = $datos['reference'] ?? null;

      if (!$reference) {
        return response()->json(['message' => 'Evento sin referencia, ignorado.'], 200);
      }

      $transaccion = WompiTransaccion::where('reference', $reference)->first();

      if (!$transaccion) {
        Log::info('Webhook Wompi: referencia desconocida.', ['reference' => $reference]);

        return response()->json(['message' => 'Referencia no registrada.'], 200);
      }

      $this->sincronizarTransaccionWompi($transaccion, $datos, 'WEBHOOK');

      return response()->json(['message' => 'Evento procesado.'], 200);
    } catch (\Exception $e) {
      Log::error('Error al procesar el webhook de Wompi: ' . $e->getMessage());

      return response()->json(['error' => 'Error al procesar el evento.'], 500);
    }
  }

  /**
   * Punto de entrada del comando `wompi:sync-pending`.
   */
  public function sincronizarDesdeComando(WompiTransaccion $transaccion, array $datos, string $origen): void
  {
    $this->sincronizarTransaccionWompi($transaccion, $datos, $origen);
  }

  // ---------------------------------------------------------------------------
  // Helpers de compra de planes
  // ---------------------------------------------------------------------------

  /**
   * Actualiza la transacción con lo reportado por Wompi y, si el pago supera
   * TODAS las validaciones, crea UNA SOLA VEZ la solicitud para el
   * Administrador VT. El plan NO se activa aquí.
   */
  private function sincronizarTransaccionWompi(WompiTransaccion $transaccion, array $datos, string $origen): void
  {
    $wompiService  = app(WompiService::class);
    $auditoria     = app(AuditoriaPlanesService::class);
    $notificaciones = app(NotificacionesPlanesService::class);

    $estadoAnterior = $transaccion->status;
    $estado         = $wompiService->normalizarEstado($datos['status'] ?? null);

    $transaccion->update([
      'transactionId'       => $datos['id'] ?? $transaccion->transactionId,
      'paymentMethod'       => $datos['payment_method_type'] ?? $transaccion->paymentMethod,
      'paymentMethodType'   => $datos['payment_method_type'] ?? $transaccion->paymentMethodType,
      'status'              => $estado,
      'statusMessage'       => $datos['status_message'] ?? null,
      'customerEmail'       => $datos['customer_email'] ?? $transaccion->customerEmail,
      'fechaPago'           => $datos['finalized_at'] ?? $datos['created_at'] ?? $transaccion->fechaPago,
      'respuestaWompi'      => $datos,
      'origenActualizacion' => $origen,
    ]);

    $transaccion->refresh();

    if ($estadoAnterior !== $estado) {
      $auditoria->registrar(AuditoriaPlanMensaje::PAGO_ESTADO_ACTUALIZADO, (int) $transaccion->userId, [
        'descripcion'    => "Estado del pago actualizado por {$origen}.",
        'referenciaPago' => $transaccion->reference,
        'transactionId'  => $transaccion->transactionId,
        'estadoAnterior' => $estadoAnterior,
        'estadoNuevo'    => $estado,
        'fechaPago'      => $transaccion->fechaPago,
        'planId'         => $transaccion->planId,
      ]);
    }

    // Idempotencia: transacción ya procesada => solo se refleja el estado.
    if ($transaccion->solicitudId || $transaccion->yaProcesada()) {
      if ($transaccion->solicitudId) {
        SolicitudPlanMensaje::where('id', $transaccion->solicitudId)->update(['estadoPago' => $estado]);
      }

      return;
    }

    $motivo = $this->validarPagoAprobadoWompi($transaccion, $datos, $estado);

    if ($motivo !== null) {
      $transaccion->update([
        'validacionFallida' => true,
        'motivoValidacion'  => $motivo,
      ]);

      $auditoria->incidentePago((int) $transaccion->userId, $motivo, [
        'descripcion'    => 'El pago no superó las validaciones de seguridad; la solicitud NO fue creada.',
        'referenciaPago' => $transaccion->reference,
        'transactionId'  => $transaccion->transactionId,
        'estadoAnterior' => $estadoAnterior,
        'estadoNuevo'    => $estado,
        'planId'         => $transaccion->planId,
        'detalle'        => [
          'amountInCentsRecibido' => $datos['amount_in_cents'] ?? null,
          'currencyRecibida'      => $datos['currency'] ?? null,
          'statusRecibido'        => $datos['status'] ?? null,
        ],
      ]);

      return;
    }

    DB::transaction(function () use ($transaccion, $origen, $auditoria, $notificaciones) {
      // Bloqueo de la fila: dos webhooks simultáneos se serializan aquí.
      $bloqueada = WompiTransaccion::lockForUpdate()->find($transaccion->id);

      if (!$bloqueada || $bloqueada->solicitudId || $bloqueada->yaProcesada()) {
        return;
      }

      $plan = MensajesPlan::find($bloqueada->planId);

      $solicitud = SolicitudPlanMensaje::create([
        'userId'             => $bloqueada->userId,
        'companyId'          => $bloqueada->companyId,
        'planId'             => $bloqueada->planId,
        'planNombre'         => $plan->nombre,
        'cantidadMensajes'   => $plan->cantidadMensajes,
        'valor'              => $plan->precio,
        'metodoPago'         => 'Wompi - ' . ($bloqueada->paymentMethodType ?? 'No informado'),
        'estado'             => SolicitudPlanMensaje::PAGO_REALIZADO,
        'wompiTransaccionId' => $bloqueada->id,
        'estadoPago'         => WompiTransaccion::APPROVED,
        'referenciaPago'     => $bloqueada->reference,
      ]);

      $bloqueada->update([
        'solicitudId'       => $solicitud->id,
        'procesadaEn'       => now(),
        'validacionFallida' => false,
        'motivoValidacion'  => null,
      ]);

      $auditoria->registrar(AuditoriaPlanMensaje::SOLICITUD_CREADA, (int) $bloqueada->userId, [
        'solicitudId'      => $solicitud->id,
        'descripcion'      => "Pago aprobado en Wompi (ref. {$bloqueada->reference}). Pendiente de aprobación del Administrador VT.",
        'planId'           => $plan->id,
        'planNombre'       => $plan->nombre,
        'cantidadMensajes' => $plan->cantidadMensajes,
        'referenciaPago'   => $bloqueada->reference,
        'transactionId'    => $bloqueada->transactionId,
        'estadoNuevo'      => SolicitudPlanMensaje::PAGO_REALIZADO,
        'fechaPago'        => $bloqueada->fechaPago,
        'observaciones'    => "Origen de la confirmación: {$origen}.",
        'detalle'          => [
          'transactionId'     => $bloqueada->transactionId,
          'reference'         => $bloqueada->reference,
          'paymentMethodType' => $bloqueada->paymentMethodType,
          'amount'            => $bloqueada->amount,
          'currency'          => $bloqueada->currency,
        ],
      ]);

      $notificaciones->pagoAprobado(
        (int) $bloqueada->userId,
        $bloqueada->reference,
        $bloqueada->amount,
        ['solicitudId' => $solicitud->id, 'transaccionId' => $bloqueada->id]
      );
    });
  }

  /**
   * Validaciones obligatorias antes de dar por bueno un pago.
   * Devuelve el motivo del rechazo, o null si todo es correcto.
   */
  private function validarPagoAprobadoWompi(WompiTransaccion $transaccion, array $datos, string $estado): ?string
  {
    if ($estado !== WompiTransaccion::APPROVED) {
      return "El estado recibido es {$estado}, no APPROVED.";
    }

    if (empty($transaccion->reference)) {
      return 'La transacción no tiene referencia registrada.';
    }

    if (empty($transaccion->userId)) {
      return 'La transacción no está asociada a ningún usuario.';
    }

    $plan = MensajesPlan::find($transaccion->planId);

    if (!$plan) {
      return "El plan {$transaccion->planId} no existe.";
    }

    // El importe autoritativo es el de la BD, no el que llega en la petición.
    $esperadoEnCentavos = (int) round(((float) $plan->precio) * 100);
    $recibidoEnCentavos = (int) ($datos['amount_in_cents'] ?? $transaccion->amountInCents);

    if ($recibidoEnCentavos !== $esperadoEnCentavos) {
      return "El monto pagado ({$recibidoEnCentavos}) no coincide con el valor del plan ({$esperadoEnCentavos}).";
    }

    $monedaEsperada = strtoupper((string) config('services.wompi.currency', 'COP'));
    $monedaRecibida = strtoupper((string) ($datos['currency'] ?? $transaccion->currency));

    if ($monedaRecibida !== $monedaEsperada) {
      return "La moneda recibida ({$monedaRecibida}) no es la configurada ({$monedaEsperada}).";
    }

    $existente = SolicitudPlanMensaje::where('referenciaPago', $transaccion->reference)->first();

    if ($existente) {
      return "La referencia {$transaccion->reference} ya generó la solicitud {$existente->id}.";
    }

    if ($transaccion->yaProcesada()) {
      return 'La transacción ya fue procesada anteriormente.';
    }

    return null;
  }

  /**
   * Desglose subtotal / IVA / total a partir del precio del plan (IVA incluido).
   */
  private function desglosarImportesPlan(float $total): array
  {
    $porcentaje = (float) config('services.wompi.iva_porcentaje', 0);

    if ($porcentaje <= 0) {
      return [
        'subtotal'      => round($total, 2),
        'ivaPorcentaje' => 0,
        'iva'           => 0,
        'total'         => round($total, 2),
      ];
    }

    $subtotal = $total / (1 + ($porcentaje / 100));

    return [
      'subtotal'      => round($subtotal, 2),
      'ivaPorcentaje' => $porcentaje,
      'iva'           => round($total - $subtotal, 2),
      'total'         => round($total, 2),
    ];
  }

  /**
   * Empresa del usuario (informativo para el listado del administrador).
   */
  private function resolverCompanyIdPlan(int $userId): ?int
  {
    try {
      $companyId = DB::table('activation_company_users')
        ->where('user_id', $userId)
        ->orderByDesc('id')
        ->value('company_id');

      return $companyId ? (int) $companyId : null;
    } catch (\Throwable $e) {
      return null;
    }
  }
}
