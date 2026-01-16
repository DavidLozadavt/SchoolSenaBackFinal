<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;

class FCMService
{


  public static function send($title, $body, $deviceToken)
  {


    $fcmUrl = 'https://fcm.googleapis.com/v1/projects/admin-vt-b169f/messages:send';
    $token = is_array($deviceToken) ? $deviceToken : [$deviceToken];
    $bearer_token = self::generateGoogleAccessToken();


    foreach ($token as $item) {
      $fcmNotification = [
        'message' => [
          "token" => $item,
          'notification' => [
            "title" =>  $title,
            "body" => $body,
          ],
        ]
      ];

      $headers = [
        'Authorization: Bearer ' . $bearer_token,
        'Content-Type: application/json'
      ];

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $fcmUrl);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));

      $result = curl_exec($ch);

      if ($result === false) {
        return false;
      }

      curl_close($ch);


      $resultData = json_decode($result, true);
    
      if (json_last_error() !== JSON_ERROR_NONE) {

        return false;
      }
    }

    return true;
  }

  public static function generateGoogleAccessToken()
  {
    $credentialsFilePath = base_path('storage/firebase/service-account.json');
    $credentials = json_decode(file_get_contents($credentialsFilePath), true);

    $now_seconds = time();
    $payload = [
      "iss" => $credentials['client_email'],
      "sub" => $credentials['client_email'],
      "aud" => "https://oauth2.googleapis.com/token",
      "iat" => $now_seconds,
      "exp" => $now_seconds + 3600,
      "scope" => "https://www.googleapis.com/auth/firebase.messaging"
    ];

    $jwt = JWT::encode($payload, $credentials['private_key'], 'RS256');


    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $jwt,
    ]));

    $result = curl_exec($ch);

    curl_close($ch);


    $response = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {


      return null;
    }

    if (isset($response['error'])) {
    }

    return $response['access_token'] ?? null;
  }
}
