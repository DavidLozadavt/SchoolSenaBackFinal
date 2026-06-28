<?php

namespace App\Http\Controllers\gestion_rol_permisos;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class AsignacionRolPermiso extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Permission::query()->orderBy('name')->get());
    }

    public function permissionsByRole(Request $request): JsonResponse
    {
        $request->validate([
            'rol' => 'required|integer|exists:roles,id',
        ]);

        $role = Rol::findOrFail($request->input('rol'));

        return response()->json($role->getPermissionNames()->values()->all());
    }

    public function assignFunctionality(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idRol' => 'required|integer|exists:roles,id',
            'funciones' => 'nullable|array',
            'funciones.*' => 'integer|exists:permissions,id',
        ]);

        $role = Rol::findOrFail($validated['idRol']);
        $permissionIds = $validated['funciones'] ?? [];

        $permissions = Permission::whereIn('id', $permissionIds)->get();
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Permisos asignados correctamente',
            'role_id' => $role->id,
            'role_name' => $role->name,
            'permissions_count' => $permissions->count(),
            'permissions' => $permissions->pluck('name')->values()->all(),
        ]);
    }
}
