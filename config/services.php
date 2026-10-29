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
     
    'livekit' => [
        'key' => env('LIVEKIT_API_KEY'),
        'secret' => env('LIVEKIT_API_SECRET'),
        'url' => env('LIVEKIT_URL'),
    ],

    'erp' => [
        'base_url' => env('ERP_BASE_URL', 'http://localhost:8001/api'),
        'service_token' => env('ERP_SERVICE_TOKEN'),
    ],

    // Respaldo local de la pasarela Wompi cuando el ERP no responde (ver WompiConfigProvider).
    'wompi' => [
        'public_key' => env('WOMPI_PUBLIC_KEY', env('WOMPI_PUBLIC_KEY_TEST')),
        'private_key' => env('WOMPI_PRIVATE_KEY', env('WOMPI_PRIVATE_KEY_TEST')),
        'integrity_secret' => env('WOMPI_INTEGRITY_SECRET', env('WOMPI_TEST_INTEGRITY')),
        'events_secret' => env('WOMPI_EVENTS_SECRET', env('WOMPI_TEST_EVENTS')),
        'habilitar_pse' => env('WOMPI_HABILITAR_PSE', true),
        'habilitar_tarjetas' => env('WOMPI_HABILITAR_TARJETAS', true),
    ],

    'school' => [
        'frontend_url' => env('FRONTEND_URL', 'https://school-vt.virtualt.org'),
    ],

    // Dominio del frontend de School: a donde apunta el QR de las escarapelas.
    'frontend' => [
        'url' => env('FRONTEND_URL'),
    ],

    // Token compartido con el ERP, la pasarela y RentUs (cabecera X-Bridge-Token).
    // Se lee aqui y no con env() en el controlador: con config:cache env() devuelve null.
    'bridge' => [
        'token' => env('BRIDGE_SECRET_TOKEN', 'VirtualT_Bridge_Secret_2026'),
        'nexi_url' => env('NEXI_API_URL', 'http://127.0.0.1:8001'),
        'rentus_url' => env('RENTUS_API_URL', 'http://127.0.0.1:8004'),
    ],
];
