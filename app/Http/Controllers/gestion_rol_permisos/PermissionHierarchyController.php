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
        // Construir el árbol completo empezando por permisos raíz
        $rootPermissions = Permission::whereNull('idPermissionPadre')
            ->orderBy('id', 'desc')
            ->get();

        $permissionsTree = $this->buildFullPermissionsTree($rootPermissions);

        // Mapear el árbol a la forma que espera el frontend
        $menuTree = $this->mapTreeToFrontend($permissionsTree);

        // Asegurar que el Dashboard (si existe) aparezca primero por visibilidad
        $menuTree = $this->ensureDashboardFirst($menuTree);

        return response()->json($menuTree);
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
     * Updates simple attributes of a permission (e.g. description, icon, path, etc.).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'descripcion' => ['nullable', 'string'],
            'idPermissionPadre' => ['nullable', 'integer', 'exists:permissions,id'],
            'icon' => ['nullable', 'string'],
            'path' => ['nullable', 'string']
        ]);

        $permission = Permission::findOrFail($id);

        $newDescription = $data['description'] ?? $data['descripcion'] ?? null;
        $hasName = array_key_exists('name', $data) && trim((string) $data['name']) !== '';
        $hasDescription = $newDescription !== null;
        $hasIcon = array_key_exists('icon', $data);
        $hasPath = array_key_exists('path', $data);
        $hasParent = array_key_exists('idPermissionPadre', $data);

        if (!$hasName && !$hasDescription && !$hasIcon && !$hasPath && !$hasParent) {
            return response()->json([
                'message' => 'No hay campos para actualizar.'
            ], 400);
        }

        if ($hasName) {
            $permission->name = strtoupper(preg_replace('/\s+/', '_', trim($data['name'])));
        }

        if ($hasDescription) {
            $permission->description = $newDescription;
        }

        if (isset($data['icon'])) {
            $permission->icon = $data['icon'];
        }
        if (isset($data['path'])) {
            $permission->path = $data['path'];
        }
        if (isset($data['idPermissionPadre'])) {
            $permission->idPermissionPadre = $data['idPermissionPadre'];
        }

        $permission->save();

        return response()->json([
            'message' => 'Permiso actualizado correctamente.',
            'permission' => $permission->load('parent', 'children'),
        ]);
    }

    /**
     * POST /api/permisos/crear
     *
     * Creates a new permission.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'unique:permissions,name'],
            'guard_name'        => ['nullable', 'string'],
            'description'       => ['nullable', 'string'],
            'idPermissionPadre' => ['nullable', 'integer', 'exists:permissions,id'],
            'icon'              => ['nullable', 'string'],
            'path'              => ['nullable', 'string']
        ]);

        $normalizedName = strtoupper(preg_replace('/\s+/', '_', trim($data['name'])));

        $permission = Permission::create([
            'name'              => $normalizedName,
            'guard_name'        => $data['guard_name'] ?? 'web',
            'description'       => $data['description'] ?? null,
            'idPermissionPadre' => $data['idPermissionPadre'] ?? null,
            'icon'              => $data['icon'] ?? null,
            'path'              => $data['path'] ?? null
        ]);

        // Reset cached permissions (Spatie)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message'    => 'Permiso creado correctamente.',
            'permission' => $permission->load('parent', 'children'),
        ], 201);
    }
    /**
     * Get all permissions with their hierarchy (no user filtering)
     */
    public function getAllPermissionsHierarchy(): JsonResponse
    {
        // Obtener TODOS los permisos raíz (sin filtrar por is_menu_item)
        $rootPermissions = Permission::whereNull('idPermissionPadre')
            ->get();

        // Construir el árbol completo y mapearlo al formato del frontend
        $permissionsTree = $this->buildFullPermissionsTree($rootPermissions);
        $menuTree = $this->mapTreeToFrontend($permissionsTree);

        // Asegurar que el Dashboard (si existe) aparezca primero por visibilidad
        $menuTree = $this->ensureDashboardFirst($menuTree);

        return response()->json($menuTree);
    }

    /**
     * Move a dashboard-like node to the front of the menu array if present.
     * Detection is fuzzy: checks title, path and requiredPermissions for the word "dashboard",
     * and common paths like "/" or "/dashboard".
     *
     * @param array $menu
     * @return array
     */
    private function ensureDashboardFirst(array $menu): array
    {
        $dashboardIndex = null;
        foreach ($menu as $i => $node) {
            $title = isset($node['title']) ? strtolower($node['title']) : '';
            $path = $node['path'] ?? '';
            $required = $node['requiredPermissions'] ?? [];
            $reqString = strtolower(implode(' ', $required));

            if (strpos($title, 'dashboard') !== false
                || $path === '/'
                || $path === '/dashboard'
                || strpos($reqString, 'dashboard') !== false) {
                $dashboardIndex = $i;
                break;
            }
        }

        if ($dashboardIndex !== null && $dashboardIndex > 0) {
            $dashboard = $menu[$dashboardIndex];
            array_splice($menu, $dashboardIndex, 1);
            array_unshift($menu, $dashboard);
        }

        return $menu;
    }

    private function buildFullPermissionsTree($permissions)
    {
        $tree = [];
        foreach ($permissions as $permission) {
            $children = Permission::where('idPermissionPadre', $permission->id)
                ->orderBy('name')
                ->get();

            $builtChildren = $this->buildFullPermissionsTree($children);

            $node = [
                'id'          => $permission->id,
                'name'        => $permission->name,
                'description' => $permission->description,
                'icon'        => $permission->icon,
                'path'        => $permission->path,
            ];

            if (!empty($builtChildren)) {
                $node['children'] = $builtChildren;
            }

            $tree[] = $node;
        }
        return $tree;
    }

    /**
     * Map a permissions tree (id,name,description,icon,path,children)
     * to the frontend menu structure expected by the client.
     * Each node becomes: { title, icon, path, requiredPermissions: [name], children }
     */
    private function mapTreeToFrontend(array $permissionsTree): array
    {
        $result = [];
        foreach ($permissionsTree as $node) {
            $mapped = [
                'title' => $node['description'] ?? $node['name'],
                'icon' => $node['icon'] ?? null,
                'path' => $node['path'] ?? '#',
                'requiredPermissions' => isset($node['name']) ? [$node['name']] : [],
            ];

            if (!empty($node['children'])) {
                $mapped['children'] = $this->mapTreeToFrontend($node['children']);
            }

            $result[] = $mapped;
        }

        return $result;
    }
}
