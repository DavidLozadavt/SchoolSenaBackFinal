<?php

namespace App\Http\Controllers\gestion_rol_permisos;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionHierarchyController extends Controller
{
    /**
     * GET /api/permisos_jerarquia
     *
     * Returns all permissions with parent/children info for tree building.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::with('children')
            ->orderBy('name')
            ->get();

        return response()->json($permissions);
    }

    /**
     * POST /api/permissions/{id}/set-parent
     *
     * Assigns a parent to a permission.
     * Payload: { "parent_id": <int|null> }
     */
    public function setParent(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('permissions', 'id'),
            ],
        ]);

        $permission = Permission::findOrFail($id);
        $newParentId = $data['parent_id'] ?? null;

        // Cannot be its own parent
        if ($newParentId !== null && $newParentId === $permission->id) {
            return response()->json([
                'message' => 'Un permiso no puede ser padre de sí mismo.'
            ], 422);
        }

        // Cycle detection: the proposed parent must NOT be a descendant of this permission
        if ($newParentId !== null) {
            $descendantIds = $permission->getDescendantIds();
            if (in_array($newParentId, $descendantIds, true)) {
                return response()->json([
                    'message' => 'Relación cíclica detectada – el padre propuesto es un descendiente de este permiso.'
                ], 422);
            }
        }

        $permission->idPermissionPadre = $newParentId;
        $permission->save();

        return response()->json([
            'message' => 'Padre actualizado correctamente.',
            'permission' => $permission->load('parent', 'children'),
        ]);
    }

    /**
     * PUT|POST /api/permisos/{id}
     *
     * Updates simple attributes of a permission (e.g. description).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $permission = Permission::findOrFail($id);

        $newDescription = $data['description'] ?? $data['descripcion'] ?? null;

        if ($newDescription === null) {
            return response()->json([
                'message' => 'No hay campos para actualizar.'
            ], 400);
        }

        $permission->description = $newDescription;
        $permission->save();

        return response()->json([
            'message' => 'Descripción actualizada correctamente.',
            'permission' => $permission->load('parent', 'children'),
        ]);
    }
}
