<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Tymon\JWTAuth\Facades\JWTAuth;

$email = $argv[1] ?? 'pinzadiaz@gmail.com';
$password = $argv[2] ?? 'diazsirley';

$token = JWTAuth::attempt(['email' => $email, 'password' => $password]);
echo $token ? "LOGIN OK token=" . substr($token, 0, 40) . "...\n" : "LOGIN FAIL (401)\n";
