<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Otps;
use App\Models\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    /**
     * Si hay un JWT válido en la petición, el correo queda fijo al de la cuenta
     * autenticada (usuario.email, el sincronizado desde nexiservice/ERP) y se
     * ignora cualquier email que venga en el body. Null si no hay sesión (flujo
     * público de "olvidé mi contraseña" antes de iniciar sesión).
     */
    private function authenticatedEmail(): ?string
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            return $user?->email;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Propaga el nuevo hash de contraseña a nexiservice y RentUs cuando el
     * usuario la cambia en School. Fire-and-forget: si un destino falla, no
     * rompe el cambio de contraseña local.
     */
    private function syncPasswordToSiblings(string $email, string $hash): void
    {
        $bridgeToken = env('BRIDGE_SECRET_TOKEN', 'VirtualT_Bridge_Secret_2026');
        $targets = [
            env('NEXI_API_URL', 'http://localhost:8001') . '/api/integration/sync-password',
            env('RENTUS_API_URL', 'http://localhost:8004') . '/api/integration/sync-password',
        ];

        foreach ($targets as $url) {
            try {
                (new \GuzzleHttp\Client())->post($url, [
                    'headers' => ['X-Bridge-Token' => $bridgeToken, 'Accept' => 'application/json'],
                    'json' => ['email' => $email, 'contrasena_hash' => $hash],
                    'timeout' => 5,
                ]);
            } catch (\Exception $e) {
                \Log::error("Error sincronizando contraseña hacia {$url}: " . $e->getMessage());
            }
        }
    }

    /**
     * Recibe la sincronización de contraseña cuando el usuario la cambia en
     * nexiservice o RentUs. No dispara nada de vuelta (evita ping-pong).
     */
    public function syncPassword(Request $request)
    {
        $receivedToken = $request->header('X-Bridge-Token');
        $expectedToken = env('BRIDGE_SECRET_TOKEN', 'VirtualT_Bridge_Secret_2026');

        if (empty($receivedToken) || $receivedToken !== $expectedToken) {
            return response()->json(['error' => 'No autorizado.'], 401);
        }

        $request->validate([
            'email' => 'required|email',
            'contrasena_hash' => 'required|string',
        ]);

        $updated = DB::table('usuario')
            ->where('email', $request->email)
            ->update(['contrasena' => $request->contrasena_hash]);

        return response()->json(['matched' => (bool) $updated]);
    }

    public function sendOtp(Request $request)
    {
        try {
            $lockedEmail = $this->authenticatedEmail();

            $request->validate([
                'email' => $lockedEmail ? 'nullable|email' : 'required|email'
            ]);

            $email = $lockedEmail ?? $request->email;

            \Log::info('Enviando OTP a:', ['email' => $email]);

            $userExists = DB::table('persona')->where('email', $email)->exists()
                || DB::table('usuario')->where('email', $email)->exists();
            
            $responseMessage = 'Si el correo existe en nuestro sistema, recibirás un código de verificación en tu correo electrónico.';
            
            if (!$userExists) {
                \Log::info('Usuario no existe (respuesta segura)');
                return response()->json([
                    'message' => $responseMessage,
                    'email' => $email
                ], 200);
            }

            DB::table('otps')
                ->where('identifier', $email)
                ->where('valid', 1)
                ->update(['valid' => 0]);

            $otp = rand(100000, 999999);
            
            \Log::info('OTP generado:', ['otp' => $otp]);

            DB::table('otps')->insert([
                'identifier' => $email,
                'token' => (string) $otp,
                'validity' => 10,
                'valid' => 1,
                'created_at' => now()
            ]);

            \Log::info('OTP guardado en BD');

            try {
                Mail::send('mails.verification-otp', ['otp' => $otp], function ($message) use ($email) {
                    $message->to($email)
                            ->subject('Código de verificación - Recuperación de contraseña');
                });
                
                \Log::info('Email enviado exitosamente');
            } catch (\Exception $e) {
                \Log::error('Error enviando email: ' . $e->getMessage());
            }

            return response()->json([
                'message' => $responseMessage,
                'email' => $email
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en sendOtp: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            \Log::info('=== VERIFICANDO OTP ===');

            $lockedEmail = $this->authenticatedEmail();

            $request->validate([
                'email' => $lockedEmail ? 'nullable|email' : 'required|email',
                'otp' => 'required|string|size:6'
            ]);

            $email = $lockedEmail ?? $request->email;
            $otp = $request->otp;

            \Log::info('Datos recibidos:', ['email' => $email, 'otp' => $otp]);

            $otpRecord = DB::table('otps')
                ->where('identifier', $email)
                ->where('token', $otp)
                ->where('valid', 1)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otpRecord) {
                \Log::warning('OTP no encontrado o inválido');
                return response()->json([
                    'message' => 'Código inválido. Verifica los 6 dígitos.',
                    'error' => 'invalid_otp'
                ], 400);
            }

            \Log::info('OTP encontrado:', [
                'id' => $otpRecord->id,
                'created_at' => $otpRecord->created_at
            ]);

            $createdAt = strtotime($otpRecord->created_at);
            $now = time();
            $minutesPassed = floor(($now - $createdAt) / 60);
            
            \Log::info('Tiempo transcurrido:', ['minutes' => $minutesPassed, 'validity' => $otpRecord->validity]);
            
            if ($minutesPassed > $otpRecord->validity) {
                \Log::warning('OTP expirado');
                DB::table('otps')->where('id', $otpRecord->id)->update(['valid' => 0]);
                return response()->json([
                    'message' => 'El código ha expirado. Solicita uno nuevo.',
                    'error' => 'expired_otp'
                ], 400);
            }

            DB::table('otps')->where('id', $otpRecord->id)->update(['valid' => 0]);

            $resetToken = Str::random(64);
            
            \Log::info('Token generado:', ['token' => $resetToken]);

            DB::table('password_resets')->where('email', $email)->delete();
            DB::table('password_resets')->insert([
                'email' => $email,
                'token' => $resetToken,
                'created_at' => now()->format('Y-m-d H:i:s')
            ]);

            \Log::info('OTP verificado exitosamente');

            return response()->json([
                'message' => 'Código verificado correctamente',
                'reset_token' => $resetToken
            ], 200);

        } catch (\Exception $e) {
            \Log::error('ERROR en verifyOtp:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => 'server_error'
            ], 500);
        }
    }

   public function resetPassword(Request $request)
{
    try {
        $lockedEmail = $this->authenticatedEmail();

        $request->validate([
            'email' => $lockedEmail ? 'nullable|email' : 'required|email|exists:persona,email',
            'token' => 'required|string',
            'password' => 'required|min:8|confirmed'
        ]);

        $email = $lockedEmail ?? $request->email;
        $token = $request->token;
        $password = $request->password;

        if ($lockedEmail) {
            $user = \App\Models\User::where('email', $lockedEmail)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no encontrado'
                ], 404);
            }
        } else {
            $findUser = DB::table('persona')
                    ->where('email', $email)
                    ->first();

            if (!$findUser) {
                return response()->json([
                    'message' => 'Persona no encontrada'
                ], 404);
            }

            $user = \App\Models\User::where('idpersona', $findUser->id)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'No se encontró un usuario asociado a esta persona'
                ], 404);
            }
        }

        \Log::info('Reseteando contraseña para:', ['email' => $email]);

        $resetRecord = DB::table('password_resets')
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (!$resetRecord) {
            \Log::warning('Token no encontrado');
            return response()->json([
                'message' => 'Token de recuperación inválido o expirado',
                'error' => 'invalid_token'
            ], 400);
        }

        \Log::info('Token encontrado:', ['created_at' => $resetRecord->created_at]);

        $createdAt = strtotime($resetRecord->created_at);
        $now = time();
        $hoursPassed = floor(($now - $createdAt) / 3600);
        
        \Log::info('Horas desde creación:', ['hours' => $hoursPassed]);
        
        if ($hoursPassed > 24) {
            \Log::warning('Token expirado');
            DB::table('password_resets')->where('email', $email)->delete();
            return response()->json([
                'message' => 'El enlace de recuperación ha expirado',
                'error' => 'expired_token'
            ], 400);
        }
        // $user ya fue resuelto arriba (cuenta autenticada o vía persona.email)
        $user->contrasena = Hash::make($password);
        $user->updated_at = now();
        $user->save();

        \Log::info('Contraseña actualizada para usuario ID:', ['user_id' => $user->id]);

        $this->syncPasswordToSiblings($user->email, $user->contrasena);

        try {
            // Ya tenemos el objeto $user, podemos usarlo directamente
            \Log::info('Verificando activación para:', ['user_id' => $user->id]);

                // Buscar la activación que esté específicamente en estado 18
                $activacion = \App\Models\ActivationCompanyUser::where('user_id', $user->id)
                    ->where('state_id', 18)
                    ->first();

                if ($activacion) {
                    \Log::info('Cambiando state_id de 18 a 1 para usuario:', ['user_id' => $user->id]);

                    $activacion->state_id = 1;
                    $activacion->save();

                    \Log::info('State_id actualizado exitosamente');

                    // Actualizar roles según el tipo de usuario
                    if ($activacion->hasRole('ESTUDIANTEUP')) {
                        $activacion->removeRole('ESTUDIANTEUP');
                        $activacion->assignRole('APRENDIZ');
                        \Log::info('Rol ESTUDIANTEUP revertido a APRENDIZ SENA');
                    } elseif ($activacion->hasRole('DOCENTEUP')) {
                        $activacion->removeRole('DOCENTEUP');
                        $activacion->assignRole('INSTRUCTOR SENA');
                        \Log::info('Rol DOCENTEUP revertido a INSTRUCTOR SENA');
                    }

                    DB::table('password_resets')->where('email', $email)->delete();
                    DB::table('otps')->where('identifier', $email)->delete();

                    return response()->json([
                        'message' => '¡Proceso completado! Contraseña actualizada y perfil activado correctamente.',
                        'profile_completed' => true
                    ], 200);
                } else {
                    \Log::info('El usuario no tiene activación en estado 18.', ['user_id' => $user->id]);
            }
        } catch (\Exception $e) {
            \Log::error('Error actualizando state_id: ' . $e->getMessage());
        }
        // ===== FIN =====

        DB::table('password_resets')->where('email', $email)->delete();
        DB::table('otps')->where('identifier', $email)->delete();

        \Log::info('Registros limpiados');

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión con tu nueva contraseña.',
            'profile_completed' => false
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (\Exception $e) {
        \Log::error('Error en resetPassword: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error interno del servidor: ' . ($e->getMessage())
        ], 500);
    }
}
}