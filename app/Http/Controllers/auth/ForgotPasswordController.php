<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Otps;
use App\Models\PasswordReset;
use App\Models\usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{

    public function sendOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email'
            ]);

            $email = $request->email;
            
            \Log::info('Enviando OTP a:', ['email' => $email]);

            $userExists = DB::table('usuario')->where('email', $email)->exists();
            
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
                Mail::raw("Tu código de verificación es: $otp\n\nEste código es válido por 10 minutos.\n\nSi no solicitaste este código, por favor ignora este mensaje.", function ($message) use ($email) {
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
            
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string|size:6'
            ]);

            $email = $request->email;
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
        $request->validate([
            'email' => 'required|email|exists:usuario,email',
            'token' => 'required|string',
            'password' => 'required|min:8|confirmed'
        ]);

        $email = $request->email;
        $token = $request->token;
        $password = $request->password;

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

        $affected = DB::table('usuario')
            ->where('email', $email)
            ->update([
                'contrasena' => Hash::make($password),
                'updated_at' => now()
            ]);

        \Log::info('Contraseña actualizada:', ['affected_rows' => $affected]);

        if ($affected === 0) {
            \Log::warning('No se actualizó ninguna fila');
            return response()->json([
                'message' => 'No se pudo actualizar la contraseña',
                'error' => 'update_failed'
            ], 400);
        }

        // ===== Cambiar state_id de 18 a 1 si aplica =====
        try {
            // Buscar el usuario en la tabla `usuario`
            $user = \App\Models\User::where('email', $email)->first();

            if ($user) {
                \Log::info('Usuario encontrado:', ['user_id' => $user->id]);

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
                    if ($activacion->hasRole('APRENDIZUP')) {
                        $activacion->removeRole('APRENDIZUP');
                        $activacion->assignRole('APRENDIZ SENA');
                        \Log::info('Rol APRENDIZUP revertido a APRENDIZ SENA');
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
            } else {
                \Log::warning('Usuario no encontrado en tabla usuario con email:', ['email' => $email]);
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

    } catch (\Exception $e) {
        \Log::error('Error en resetPassword: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error interno del servidor: ' . ($e->getMessage())
        ], 500);
    }
}
}