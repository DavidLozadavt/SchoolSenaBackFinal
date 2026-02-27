<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\Person;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\UploadedFile;  
use App\Util\KeyUtil;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct()
    {
    }

    public function logged()
    {

        $response = Session::get('usuario');
        return response($response);
    }

    public function setCompany($idUserActive)
    {
        $id = auth()->id();

        $userActivate = ActivationCompanyUser::with('company')
            ->active()
            ->byUser($id)
            ->findOrFail($idUserActive);

        $permissionsName = $this->permissionsToString($userActivate->getAllPermissions());

        $response = new \stdClass();
        $response->user = Person::with('ciudad.departamento', 'ciudadNac.departamento', 'ciudadUbicacion.departamento', 'usuario.activationCompanyUsers')->where('id', auth()->user()->idpersona)->first();
        $response->permission = $permissionsName;
        $response->userActivate = $userActivate;

        Session::put('company_id', $userActivate->company_id);
        Session::put('user_activate_id', $userActivate->id);
        Session::put('permissions', $permissionsName);
        Session::put('usuario', json_encode($response));
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


    private function permissionsToString($permissions)
    {
        $permissions = collect($permissions)->map(function ($permission) {
            return $permission->name;
        });
        return implode(',', $permissions->toArray());
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
}
