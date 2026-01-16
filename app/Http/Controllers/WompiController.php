<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionWompi;
use App\Util\KeyUtil;
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
}
