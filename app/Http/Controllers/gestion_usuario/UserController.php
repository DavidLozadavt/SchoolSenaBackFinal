<?php

namespace App\Http\Controllers\gestion_usuario;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\GrupoChat;
use App\Models\Person;
use App\Models\User;
use App\Util\KeyUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session as FacadesSession;

class UserController extends Controller
{
    public function getUsers()
    {
        $id =  KeyUtil::idCompany();
        $user = ActivationCompanyUser::with('company', 'user', 'user.persona', 'roles', 'estado')
            ->where('company_id', $id)
            ->get();

        return response()->json($user);
    }


    public function getUsersPaginated(Request $request)
    {
        $id = KeyUtil::idCompany();
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 15);

        $query = ActivationCompanyUser::with('company', 'user', 'user.persona', 'roles', 'estado');

        if (!empty($search)) {
            $query->whereHas('user.persona', function ($q) use ($search) {
                $q->where('nombre1', 'like', "%{$search}%")
                    ->orWhere('apellido1', 'like', "%{$search}%")
                    ->orWhere('nombre2', 'like', "%{$search}%")
                    ->orWhere('apellido2', 'like', "%{$search}%")
                    ->orWhere('identificacion', 'like', "%{$search}%");
            });
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

// Solo actualizar contraseña si se proporciona
        if ($request->input('contrasena')) {
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




    public function asignation(Request $request)
    {

        DB::table('model_has_roles')
            ->where('model_id', $request->idActivation)
            ->delete();
        $user = ActivationCompanyUser::find($request->input('idActivation'));
        $user->assignRole($request->input('roles', []));
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
        $persona = Person::findOrFail($request->user()->idpersona);


        $persona->rutaFoto = $this->storeLogoPersona($request, $persona->rutaFoto);


        $persona->email = $request->input('email');
        $persona->telefonoFijo = $request->input('telefonoFijo');
        $persona->celular = $request->input('celular');
        $persona->idCiudadUbicacion = $request->input('idCiudadUbicacion');
        $persona->direccion = $request->input('direccion');
        $persona->rh = $request->input('rh');
        $persona->sexo = $request->input('sexo');
        $persona->idTipoIdentificacion = $request->input('idtipoIdentificacion');

        $persona->save();

        return response()->json($persona);
    }



    /**
     * Get all users and groups by company
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersAndGroups(Request $request): JsonResponse
    {
        $activationCompanyUsers = ActivationCompanyUser::with('company', 'user', 'user.persona', 'roles', 'estado')
            ->where('company_id', KeyUtil::idCompany())
            ->active()
            ->get();

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
            'groups'                 => $groups,
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
