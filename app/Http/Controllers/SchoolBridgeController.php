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
        $expectedToken = env('BRIDGE_SECRET_TOKEN', 'VirtualT_Bridge_Secret_2026');

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
        ]);

        try {
            DB::beginTransaction();

            // 3. Crear o actualizar la empresa (colegio/institución) en School
            $company = Company::where('nit', $request->nit)->first();
            if ($company) {
                return response()->json(['error' => 'Ya existe una institución registrada con este NIT.'], 422);
            }

            // Validar que no se duplique el correo
            if (Company::where('email', $request->email)->exists() || User::where('email', $request->email)->exists()) {
                return response()->json(['error' => 'El correo electrónico ya se encuentra registrado en el sistema.'], 422);
            }

            $company = Company::create([
                'razonSocial' => $request->nombre_institucion,
                'nit' => $request->nit,
                'representanteLegal' => $request->nombre_representante,
                'direccion' => $request->direccion ?? 'No especificada',
                'email' => $request->email,
                'digitoVerificacion' => 0,
                'idCiudad' => 1, // Default o primera ciudad
            ]);

            // 4. Crear el registro en tabla 'persona' para el representante
            // Usamos el nit como identificador de persona si no se tiene
            $person = Person::create([
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
            ]);

            // 5. Crear el registro en tabla 'usuario' (Credenciales por defecto)
            // Se le genera una contraseña inicial por defecto: 'VirtualT2026!'
            $tempPassword = 'VirtualT2026!';
            $user = new User();
            $user->idpersona = $person->id;
            $user->email = $request->email;
            $user->contrasena = bcrypt($tempPassword); // Columna principal de contraseña
            $user->password = bcrypt($tempPassword);   // Laravel compatible
            $user->save();

            // 6. Vincular el usuario a la empresa mediante 'activation_company_users'
            // Con state_id = 18 (Requiere cambiar contraseña en el primer inicio de sesión)
            $activation = new ActivationCompanyUser();
            $activation->user_id = $user->id;
            $activation->company_id = $company->id;
            $activation->state_id = 18; // Estado: Cambio de contraseña requerido
            $activation->fechaInicio = now()->format('Y-m-d');
            $activation->fechaFin = now()->addYears(5)->format('Y-m-d');
            $activation->save();

            // 7. Crear el rol de 'Admin' asignado a este tenant y asignárselo
            $role = Role::firstOrCreate([
                'name' => 'Admin',
                'company_id' => $company->id,
            ], [
                'guard_name' => 'web',
            ]);

            // Sincronizar permisos estándar de administración
            $permissions = [
                'GESTION_ROLES',
                'GESTION_ROL_PERMISOS',
                'GESTION_USUARIO',
                'GESTION_PROCESOS',
                'GESTION_TIPO_DOCUMENTOS',
                'GESTION_MEDIO_PAGO',
                'GESTION_TIPO_PAGO',
                'GESTION_TIPO_TRANSACCION',
                'GESTION_CONTRATACION',
                'GESTION_CONTRATOS',
                'GESTION_PAGOS_CONTRATOS',
                'GESTION_LABORAL',
                'GESTION_CHAT',
            ];
            $role->syncPermissions($permissions);

            // Asignar el rol
            $activation->assignRole($role);

            DB::commit();

            return response()->json([
                'message' => 'Institución e inquilino administrador creados con éxito.',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'temp_password' => $tempPassword
            ], 201);

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
