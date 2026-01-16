<?php

namespace App\Http\Controllers\gestion_rol_permisos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AsignacionRolPermiso extends Controller
{


    public function index()
    {
        $permisos = Permission::All();

        return response()->json($permisos);
    }

    public function permissionsByRole(Request  $request)
    {

        $rol = $request->input('rol');

        $role = Role::findOrFail($rol);
        $groupsWithRoles = $role->getPermissionNames();


        return response()->json($groupsWithRoles);
    }


    public function assignFunctionality(Request $request)
    {

        // $user= User::find(auth()->user()->id);
        // $user->assignRole("ADMINISTRADOR_VT");
        // $user=Rol::all();

        $roles = Role::find($request->idRol);
        // dd($roles);
        DB::table('role_has_permissions')
            ->where('role_id', $request->idRol)
            ->delete();


        $roles->syncPermissions($request->input('funciones', []));
        // $permisos= auth()->user();
        // dd($request->all());

        return $roles;
    }
}
