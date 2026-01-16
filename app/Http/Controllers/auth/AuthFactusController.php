<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthFactusController extends Controller
{
    public function getTokenFactus()
    {
        try {
            $response = Http::post('https://api-sandbox.factus.com.co/oauth/token', [
                'grant_type' => 'password',
                'client_id' => env('FACTUS_CLIENT_ID'),
                'client_secret' => env('FACTUS_CLIENT_SECRET'),
                'username' => env('FACTUS_USERNAME'),
                'password' => env('FACTUS_PASSWORD'),
            ]);

            if (!$response->successful()) {
                throw new \Exception('Error al obtener token: '.$response->body());
            }

            $data = $response->json();

            Cache::put('factus_access_token', $data['access_token'], $data['expires_in'] - 60);
            Cache::put('factus_refresh_token', $data['refresh_token'], now()->addDays(30));

            return response()->json([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => $data['expires_in'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getTokenFactus: '.$e->getMessage());

            return response()->json([
                'error' => 'Error de autenticación',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function refreshToken()
    {
        try {
            $refreshToken = Cache::get('factus_refresh_token');

            if (!$refreshToken) {
                throw new \Exception('No hay refresh token disponible');
            }

            $response = Http::post('https://api-sandbox.factus.com.co/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => env('FACTUS_CLIENT_ID'),
                'client_secret' => env('FACTUS_CLIENT_SECRET'),
                'refresh_token' => $refreshToken,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Error al refrescar token: '.$response->body());
            }

            $data = $response->json();

            Cache::put('factus_access_token', $data['access_token'], $data['expires_in'] - 60);

            if (isset($data['refresh_token'])) {
                Cache::put('factus_refresh_token', $data['refresh_token'], now()->addDays(30));
            }

            return response()->json([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'expires_in' => $data['expires_in'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en refreshToken: '.$e->getMessage());

            return response()->json([
                'error' => 'Error al refrescar token',
                'message' => $e->getMessage(),
            ], 401);
        }
    }
}
