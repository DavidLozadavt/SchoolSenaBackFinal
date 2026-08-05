<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'factus' => [
        'base_url' => env('FACTUS_BASE_URL'),
        'client_id' => env('FACTUS_CLIENT_ID'),
        'client_secret' => env('FACTUS_CLIENT_SECRET'),
        'username' => env('FACTUS_USERNAME'),
        'password' => env('FACTUS_PASSWORD'),
        'timeout' => env('FACTUS_TIMEOUT', 10),
        ],

    /*
    | Pasarela de pagos Wompi — compra de planes de mensajes de WhatsApp.
    | Sandbox:    https://sandbox.wompi.co/v1     (llaves pub_test_ / prv_test_)
    | Producción: https://production.wompi.co/v1  (llaves pub_prod_ / prv_prod_)
    */
    'wompi' => [
        // Sin default: si no se define, WompiService la deriva del `modo` de la tabla.
        'base_url'         => env('WOMPI_BASE_URL'),
        'checkout_url'     => env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'),
        'public_key'       => env('WOMPI_PUBLIC_KEY'),
        'private_key'      => env('WOMPI_PRIVATE_KEY'),
        'integrity_secret' => env('WOMPI_INTEGRITY_SECRET'),
        'events_secret'    => env('WOMPI_EVENTS_SECRET'),
        'currency'         => env('WOMPI_CURRENCY', 'COP'),
        // IVA incluido en el precio del plan (0 = sin IVA). Solo afecta al
        // desglose que se muestra al usuario: el total cobrado sigue siendo
        // exactamente el precio del plan almacenado en la base de datos.
        'iva_porcentaje'   => (float) env('WOMPI_IVA_PORCENTAJE', 0),
        // URL del frontend a la que Wompi devuelve al usuario tras pagar.
        'redirect_url'     => env('WOMPI_REDIRECT_URL'),
    ],


];
