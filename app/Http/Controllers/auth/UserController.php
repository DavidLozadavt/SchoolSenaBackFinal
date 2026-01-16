<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationCompanyUser;
use App\Models\Person;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Session;

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

    private function permissionsToString($permissions)
    {
        $permissions = collect($permissions)->map(function ($permission) {
            return $permission->name;
        });
        return implode(',', $permissions->toArray());
    }
}
