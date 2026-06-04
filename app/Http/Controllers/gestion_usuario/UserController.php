<?php

namespace App\Http\Controllers\gestion_usuario;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\Contract;
use App\Models\GrupoChat;
use App\Models\Person;
use App\Models\Status;
use App\Models\User;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session as FacadesSession;

class UserController extends Controller
{
    public function getUsers()
    {
        $id = KeyUtil::idCompany();
        $query = ActivationCompanyUser::with('company', 'user', 'user.persona', 'roles', 'estado')
            ->where('company_id', $id);

        $currentUser = auth('api')->user() ?? auth()->user();
        if ($currentUser && !empty($currentUser->idCentroFormacion)) {
            $query->whereHas('user', function ($q) use ($currentUser) {
                $q->where('idCentroFormacion', $currentUser->idCentroFormacion);
            });
        }

        $user = $query->get();

        return response()->json($user);
    }


    public function getUsersPaginated(Request $request)
    {
        $id = KeyUtil::idCompany();
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 15);
        $stateId = $request->input('state_id', '');
        $roleId = $request->input('role_id', '');
        $sortOrder = $request->input('sort_order', '1');

        $idCentroFormacion = $request->input('idCentroFormacion');

        $currentUser = auth('api')->user() ?? auth()->user();
        if ($currentUser && !empty($currentUser->idCentroFormacion)) {
            $idCentroFormacion = $currentUser->idCentroFormacion;
        }

        $query = ActivationCompanyUser::with('company', 'user', 'user.persona', 'roles', 'estado')
            ->where('company_id', $id);

        if (!empty($idCentroFormacion)) {
            $query->whereHas('user', function ($q) use ($idCentroFormacion) {
                $q->where('idCentroFormacion', $idCentroFormacion);
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user.persona', function ($q2) use ($search) {
                    $q2->where('nombre1', 'like', "%{$search}%")
                        ->orWhere('apellido1', 'like', "%{$search}%")
                        ->orWhere('nombre2', 'like', "%{$search}%")
                        ->orWhere('apellido2', 'like', "%{$search}%")
                        ->orWhere('identificacion', 'like', "%{$search}%");
                })
                    ->orWhereHas('roles', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($stateId)) {
            $query->where('state_id', $stateId);
        }

        if (!empty($roleId)) {
            $query->whereHas('roles', function ($q) use ($roleId) {
                $q->where('id', $roleId);
            });
        }

        if ($sortOrder === '3') {
            $query->orderBy('id', 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $users = $query->paginate($perPage);

        return response()->json($users);
    }
    public function updateActivationCompanyUser(Request $request, $id)
    {
        $userData = $request->input('user');
        $personaData = $userData['persona'];

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $persona = $user->persona;

        if (!$persona) {
            return response()->json(['error' => 'Persona not found'], 404);
        }

        $persona->fill($personaData);
        $persona->email = $userData['email'];
        $request = Request::createFromGlobals();
        if (isset($personaData['rutaFoto']) && $personaData['rutaFoto'] && file_exists($personaData['rutaFoto'])) {
            $request->files->set('imagen', new UploadedFile($personaData['rutaFoto'], 'imagen'));
            $this->updateImagePerson($request, $userData['id']);
        }
        $persona->identificacion = $personaData['identificacion'];
        $persona->save();

        // Inicializar variable
        $passwordUpdated = false;

        // Solo actualizar contraseña si se proporciona dentro del objeto user
        if (isset($userData['contrasena']) && !empty($userData['contrasena'])) {
            $user->contrasena = bcrypt($userData['contrasena']);
            $user->save();
            $passwordUpdated = true;
        }

        $activacion = ActivationCompanyUser::where('user_id', $user->id)->first();

        // Cambiar estado de 18 a 1 solo si se actualizó la contraseña
        if ($activacion->state_id == 18 && $passwordUpdated) {
            $activacion->state_id = 1;
            $activacion->save();
        }

        // Actualizar roles según el tipo de usuario (APRENDIZ o INSTRUCTOR)
        if ($activacion->hasRole('ESTUDIANTEUP')) {
            $activacion->removeRole('ESTUDIANTEUP');
            $activacion->assignRole('APRENDIZ');
        } elseif ($activacion->hasRole('DOCENTEUP')) {
            $activacion->removeRole('DOCENTEUP');
            $activacion->assignRole('INSTRUCTOR SENA');
        }

        // $contrato = $persona->contrato;
        // if ($contrato) {
        //  $salario = Salario::where('rol_id', $docenteRoleId)->first();
        //if (!$salario) {
        //  $salario = new Salario();
        //$salario->rol_id = $docenteRoleId;
        //$salario->valor = 0;
        //$salario->save();
        //}
        //$contrato->salario()->associate($salario);
        //$contrato->save();
        //}

        $activacion->load(
            'user.persona.ubicacion',
            'user.persona.ciudad',
            'user.persona.ciudadNac.departamento',
            'user.persona.ciudadUbicacion.departamento',
            'user.persona.tipoIdentificacion',
            'roles',
            'estado'
        );

        $response = $activacion->toArray();
        $response['needs_password_update'] = $activacion->state_id == 18;
        $response['password_updated'] = $passwordUpdated;
        $response['profile_completed'] = $activacion->state_id != 18;

        return response()->json($response, 200);
    }


    public function checkProfileAccess()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $activationUser = ActivationCompanyUser::where('user_id', $user->id)->first();

        if (!$activationUser) {
            return response()->json(['error' => 'User activation not found'], 404);
        }

        return response()->json([
            'needs_password_update' => $activationUser->state_id == 18
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->all();
            $data['perfil'] = 'N/A';

            $persona = new Person($data);
            $persona->rutaFoto = $this->storeLogoPersona($request);
            $persona->save();

            $usuario = new User($data);
            $usuario->contrasena = bcrypt($request->input('contrasena'));
            $usuario->idpersona = $persona->id;
            $usuario->save();

            $activacion = new ActivationCompanyUser();
            $activacion->user_id = $usuario->id;
            $activacion->state_id = 1;
            $activacion->company_id = KeyUtil::idCompany();
            $activacion->fechaInicio = date('Y-m-d');
            $activacion->fechaFin = date('Y-m-d', strtotime('+1 year'));
            $activacion->save();

            DB::commit();

            return response()->json($usuario, 201);
        } catch (\Exception $e) {
            DB::rollBack();


            return response()->json([
                'error' => 'Ocurrió un error al guardar los datos.',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    private function storeLogoPersona(Request $request, $rutaActual = null)
    {
        if ($request->hasFile('rutaFotoFile')) {
            return '/storage/' .
                $request->file('rutaFotoFile')
                    ->store(Person::RUTA_FOTO, ['disk' => 'public']);
        }

        return $rutaActual;
    }

    private function storeFirmaDigital(Request $request, $firmaActual = null)
    {
        if ($request->hasFile('firmaDigitalFile')) {

            return '/storage/' .
                $request->file('firmaDigitalFile')
                    ->store('firmas', ['disk' => 'public']);
        }

        return $firmaActual;
    }

    /**
     * El front envía FormData: los campos numéricos opcionales llegan como '' y en MySQL estricto eso provoca error 500.
     */
    private function nullableIntFromRequest($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }





    public function asignation(Request $request)
    {

        DB::table('model_has_roles')
            ->where('model_id', $request->idActivation)
            ->delete();
        $user = ActivationCompanyUser::find($request->input('idActivation'));
        $user->assignRole($request->input('roles', []));
        $user->load('roles');
        return $user;
    }



    public function update(Request $request, $idUser)
    {
        $data = $request->all();

        $usuario = User::findOrFail($idUser);
        $persona = $usuario->persona;

        if ($persona) {
            $persona->fill($data);
            $persona->save();
        }

        $usuario->fill($data);
        if ($request->filled('contrasena')) {
            $usuario->contrasena = bcrypt($request->input('contrasena'));
        }
        $usuario->save();



        return response()->json(['message' => 'Usuario actualizado correctamente'], 200);
    }

    // /**
    //  * Remove the specified resource from storage.
    //  *
    //  * @param  int $id
    //  * @return \Illuminate\Http\Response
    //  */
    public function destroy(int $id)
    {
        ActivationCompanyUser::where('user_id', $id)->delete();
        $user = User::findOrFail($id);
        $idPersona = $user->idpersona;
        User::where('id', $id)->delete();
        Person::where('id', $idPersona)->delete();

        return response()->json([], 204);
    }


    public function updateUser(Request $request, $idUser)
    {
        $usuario = User::findOrFail($idUser);
        $persona = $usuario->persona;

        if ($persona) {

            $persona->rutaFoto = $this->storeLogoPersona($request, $persona->rutaFoto);

            $persona->email = $request->input('email');
            $persona->telefonoFijo = $request->input('telefonoFijo');
            $persona->celular = $request->input('celular');
            $persona->idCiudadUbicacion = $request->input('idCiudadUbicacion');
            $persona->direccion = $request->input('direccion');
            $persona->rh = $request->input('rh');
            $persona->sexo = $request->input('sexo');
            $persona->idTipoIdentificacion = $request->input('idTipoIdentificacion');

            $persona->save();
        }

        $usuario->fill($request->except(['rutaFotoFile', 'contrasena']));
        if ($request->filled('contrasena')) {
            $usuario->contrasena = bcrypt($request->input('contrasena'));
        }
        $usuario->save();

        return response()->json(['message' => 'Usuario actualizado correctamente'], 200);
    }





    public function updatePersona(Request $request)
    {
        $user = auth('api')->user() ?? $request->user();
        if (!$user || $user->idpersona === null || $user->idpersona === '') {
            return response()->json([
                'message' => 'No autenticado o usuario sin persona asociada'
            ], 401);
        }

        $persona = Person::find($user->idpersona);
        if (!$persona) {
            return response()->json([
                'message' => 'Persona no encontrada'
            ], 404);
        }

        try {
            $persona->rutaFoto = $this->storeLogoPersona($request, $persona->rutaFoto);
            // Firma digital es opcional; la migración puede no estar aplicada en todas las BDs.
            if (Schema::hasColumn($persona->getTable(), 'firmaDigital')) {
                $persona->firmaDigital = $this->storeFirmaDigital($request, $persona->firmaDigital);
            }

            $persona->nombre1 = $request->input('nombre1');
            $persona->nombre2 = $request->input('nombre2');
            $persona->apellido1 = $request->input('apellido1');
            $persona->apellido2 = $request->input('apellido2');
            $persona->fechaNac = $request->input('fechaNac');

            $persona->email = $request->input('email');
            $persona->telefonoFijo = $request->input('telefonoFijo');
            $persona->celular = $request->input('celular');
            $persona->idCiudadUbicacion = $this->nullableIntFromRequest($request->input('idCiudadUbicacion'));
            $persona->direccion = $request->input('direccion');
            $persona->rh = $request->input('rh');
            $persona->sexo = $request->input('sexo');
            $persona->idTipoIdentificacion = $this->nullableIntFromRequest($request->input('idtipoIdentificacion'));

            $persona->save();

            // Perfil profesional vive en `contrato`; si el usuario tiene contrato activo, lo actualiza desde su perfil.
            if ($request->has('perfilProfesional')) {
                $contratoActivo = Contract::where('idpersona', $persona->id)
                    ->where('idEstado', Status::ID_ACTIVE)
                    ->orderByDesc('fechaContratacion')
                    ->first();

                if ($contratoActivo) {
                    $val = $request->input('perfilProfesional');
                    $trimmed = is_string($val) ? trim($val) : '';
                    $contratoActivo->perfilProfesional = $trimmed !== '' ? $trimmed : 'N/A';
                    $contratoActivo->save();
                }
            }
        } catch (\Throwable $e) {
            Log::error('updatePersona', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar los datos de la persona.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json($persona);
    }



    /**
     * Get all users and groups by company
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersAndGroups(Request $request): JsonResponse
    {
        $query = ActivationCompanyUser::with('company', 'user', 'user.persona', 'roles', 'estado')
            ->where('company_id', KeyUtil::idCompany())
            ->active();

        $currentUser = auth('api')->user() ?? auth()->user();
        if ($currentUser && !empty($currentUser->idCentroFormacion)) {
            $query->whereHas('user', function ($q) use ($currentUser) {
                $q->where('idCentroFormacion', $currentUser->idCentroFormacion);
            });
        }

        $activationCompanyUsers = $query->get();

        $loggedUserId = auth()->user()->id;
        $activationUser = ActivationCompanyUser::where('user_id', $loggedUserId)->first();

        $groups = [];

        if ($activationUser) {
            $groups = GrupoChat::whereHas('participantes', function ($query) use ($activationUser) {
                $query->where('idActivationCompanyUser', $activationUser->id);
            })->get();
        }

        return response()->json([
            'activationCompanyUsers' => $activationCompanyUsers,
            'groups' => $groups,
        ]);
    }


    public function updateStatusUser(Request $request, $idUser)
    {

        $validated = $request->validate([
            'estado' => 'required|string|in:ACTIVO,INACTIVO'
        ]);


        $statusId = $validated['estado'] === 'ACTIVO' ? 1 : 2;

        $companyUser = ActivationCompanyUser::where('user_id', $idUser)->first();

        if (!$companyUser) {
            return response()->json([
                'message' => 'Usuario no encontrado en company_user'
            ], 404);
        }

        $companyUser->state_id = $statusId;
        $companyUser->save();

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'data' => $companyUser
        ]);
    }
}
