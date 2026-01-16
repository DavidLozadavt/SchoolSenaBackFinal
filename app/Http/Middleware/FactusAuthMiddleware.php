<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FactusAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        $accessToken = Cache::get('factus_access_token');

        if ($accessToken) {
            $request->headers->set('Authorization', 'Bearer ' . $accessToken);
            return $next($request);
        }
        try {
            $refreshToken = Cache::get('factus_refresh_token');

            if ($refreshToken) {
                $response = Http::post('https://api-sandbox.factus.com.co/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => env('FACTUS_CLIENT_ID'),
                    'client_secret' => env('FACTUS_CLIENT_SECRET'),
                    'refresh_token' => $refreshToken,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    Cache::put('factus_access_token', $data['access_token'], $data['expires_in'] - 60);

                    if (isset($data['refresh_token'])) {
                        Cache::put('factus_refresh_token', $data['refresh_token'], now()->addDays(30));
                    }

                    $request->headers->set('Authorization', 'Bearer ' . $data['access_token']);
                    return $next($request);
                }
            }

            $response = Http::post('https://api-sandbox.factus.com.co/oauth/token', [
                'grant_type' => 'password',
                'client_id' => env('FACTUS_CLIENT_ID'),
                'client_secret' => env('FACTUS_CLIENT_SECRET'),
                'username' => env('FACTUS_USERNAME'),
                'password' => env('FACTUS_PASSWORD'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Cache::put('factus_access_token', $data['access_token'], $data['expires_in'] - 60);
                Cache::put('factus_refresh_token', $data['refresh_token'], now()->addDays(30));

                $request->headers->set('Authorization', 'Bearer ' . $data['access_token']);
                return $next($request);
            }

            return response()->json([
                'error' => 'Authentication failed',
                'message' => 'Unable to authenticate with Factus API'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}