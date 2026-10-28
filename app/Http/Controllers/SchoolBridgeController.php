<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Person;
use App\Models\User;
use App\Models\ActivationCompanyUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class SchoolBridgeController extends Controller
{
    /**
     * Endpoint de integración para registrar una nueva institución/colegio desde el ERP.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function inscribirInstitucion(Request $request): JsonResponse
    {
        // 1. Validar el token de seguridad
        $receivedToken = $request->header('X-Bridge-Token');
        $expectedToken = config('services.bridge.token');

        if (empty($receivedToken) || $receivedToken !== $expectedToken) {
            return response()->json(['error' => 'No autorizado. Token de seguridad inválido o ausente.'], 401);
        }

        // 2. Validar el payload recibido
        $request->validate([
            'nombre_institucion' => 'required|string|max:255',
            'nombre_representante' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'nit' => 'required|string|max:45',
            'telefono' => 'nullable|string|max:45',
            'direccion' => 'nullable|string|max:255',
            'contrasena_hash' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Con la clave de NexiService el usuario entra a School con las mismas
            // credenciales. Sin ella (inscripción por formulario) se usa una clave
            // temporal y se le pide cambiarla al entrar (state_id 18).
            $conClaveNexi = $request->filled('contrasena_hash');
            $tempPassword = 'VirtualT2026!';

            // 3. Empresa por NIT, igual que inscribe-empresa del ERP business:
            // si ya existe se actualiza (así se puede reintentar sin error).
            $datosEmpresa = [
                'razonSocial' => $request->nombre_institucion,
                'representanteLegal' => $request->nombre_representante,
                'direccion' => $request->direccion ?? 'No especificada',
                'email' => $request->email,
                'telefono' => $request->telefono ?? '',
                'updated_at' => now(),
            ];

            $company = Company::where('nit', $request->nit)->first();
            if ($company) {
                DB::table('empresa')->where('id', $company->id)->update($datosEmpresa);
            } else {
                // Otra institución (otro NIT) ya usa este correo: no se mezclan.
                if (Company::where('email', $request->email)->exists()) {
                    DB::rollBack();
                    return response()->json(['error' => 'El correo electrónico ya está registrado en otra institución.'], 422);
                }

                $companyId = DB::table('empresa')->insertGetId($datosEmpresa + [
                    'nit' => $request->nit,
                    'digitoVerificacion' => 0,
                    'idCiudad' => 1,
                    'rutaLogo' => '',
                    'valorIva' => 0.00,
                    'devolucion' => 0,
                    'garantia' => 0,
                    'servicios' => 1,
                    'catalogo' => 0,
                    'productos' => 0,
                    'responsableIva' => 0,
                    'retenciones' => 0,
                    'facturaElectronica' => 0,
                    'parafiscalesEmpresa' => 0,
                    'urlDocumento' => '',
                    'created_at' => now(),
                ]);
                $company = Company::find($companyId);
            }

            // 4. Usuario por correo. Si ya existe se le pone la clave de NexiService.
            $user = User::where('email', $request->email)->first();
            $usuarioNuevo = !$user;

            if ($user) {
                if ($conClaveNexi) {
                    $user->contrasena = $request->contrasena_hash;
                    $user->save();
                }
            } else {
                // Persona del representante (identificada con el NIT).
                $person = Person::where('identificacion', $request->nit)->first() ?? Person::create([
                    'identificacion' => $request->nit,
                    'nombre1' => $request->nombre_representante,
                    'nombre2' => '',
                    'apellido1' => 'Admin',
                    'apellido2' => '',
                    'fechaNac' => now()->subYears(30)->format('Y-m-d'),
                    'idCiudadNac' => 1,
                    'idCiudad' => 1,
                    'direccion' => $request->direccion ?? 'No especificada',
                    'email' => $request->email,
                    'idTipoIdentificacion' => 1, // CC
                    'celular' => $request->telefono ?? '0000000',
                    'idCiudadUbicacion' => 1,
                    'perfil' => 'Admin',
                    'sexo' => 'M',
                    'rh' => '',
                    'firmaDigital' => '',
                    'ciudadExpedicion' => 1,
                    'tipoPersona' => 'natural',
                ]);

                $user = new User();
                $user->idpersona = $person->id;
                $user->email = $request->email;
                $user->contrasena = $conClaveNexi ? $request->contrasena_hash : bcrypt($tempPassword);
                $user->save();
            }

            // 5. Vínculo usuario-institución. Activo (1) si trae la clave de NexiService;
            // si no, 18 = debe cambiar la clave temporal al entrar.
            $activation = ActivationCompanyUser::where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->first();

            if (!$activation) {
                $activation = new ActivationCompanyUser();
                $activation->user_id = $user->id;
                $activation->company_id = $company->id;
                $activation->state_id = $conClaveNexi ? 1 : 18;
                $activation->fechaInicio = now()->format('Y-m-d');
                $activation->fechaFin = now()->addYears(5)->format('Y-m-d');
                $activation->save();
            } elseif ($conClaveNexi && (int) $activation->state_id === 18) {
                $activation->state_id = 1;
                $activation->save();
            }

            // 6. Rol de administrador de la institución
            $role = Role::firstOrCreate([
                'name' => 'ADMINISTRADOR VT',
                'guard_name' => 'web',
            ]);

            if (!$activation->hasRole($role)) {
                $activation->assignRole($role);
            }

            DB::commit();

            return response()->json(array_filter([
                'message' => 'Institución e inquilino administrador creados con éxito.',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'temp_password' => ($usuarioNuevo && !$conClaveNexi) ? $tempPassword : null,
            ], fn ($v) => $v !== null), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error registrando institución desde el puente API:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Ocurrió un error en el servidor de School Sena: ' . $e->getMessage()], 500);
        }
    }
}
