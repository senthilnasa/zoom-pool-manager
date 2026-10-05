<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Auth\Services\RoleManagementService;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaRoleController extends Controller
{
    public function __construct(
        protected RoleManagementService $roleService
    ) {}

    /**
     * Authorize that the current user has user/role management privileges.
     */
    protected function authorizeRoleManage(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || (! $user->hasRole(RoleName::adminRoles()) && ! $user->can('user.manage'))) {
            abort(403, 'Unauthorized. Administrator privileges required to manage roles and permissions.');
        }
    }

    /**
     * List all system and custom roles with user counts and permissions.
     */
    public function index(): JsonResponse
    {
        $this->authorizeRoleManage();

        $roles = $this->roleService->getAllRoles();

        return response()->json([
            'roles' => $roles,
            'core_roles' => ['Super Admin', 'Approval', 'User'],
        ]);
    }

    /**
     * Return the full permission matrix grouped by module and action.
     */
    public function permissionsMatrix(): JsonResponse
    {
        $this->authorizeRoleManage();

        $matrix = $this->roleService->getPermissionsMatrix();

        return response()->json([
            'matrix' => $matrix,
        ]);
    }

    /**
     * Create a new custom role with custom name, description, and permissions.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeRoleManage();

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:100',
        ]);

        try {
            $role = $this->roleService->createRole(
                name: $validated['name'],
                description: $validated['description'] ?? null,
                permissions: $validated['permissions'] ?? []
            );

            return response()->json([
                'success' => true,
                'role' => $role,
                'message' => "Custom role '{$role['name']}' created successfully.",
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update an existing role.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->authorizeRoleManage();

        $validated = $request->validate([
            'name' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|max:100',
        ]);

        try {
            $role = $this->roleService->updateRole($id, $validated);

            return response()->json([
                'success' => true,
                'role' => $role,
                'message' => "Role '{$role['name']}' updated successfully.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a custom role when permitted.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $this->authorizeRoleManage();

        try {
            $this->roleService->deleteRole($id);

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get effective permissions for a specific user.
     */
    public function userPermissions(int|string $id): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = auth()->user();

        $user = User::where('public_id', $id)->orWhere('id', $id)->with('department')->firstOrFail();

        // A user can view their own permissions; otherwise requires admin/dept_admin/user.view
        if ($currentUser && $currentUser->id !== $user->id) {
            $isAdmin = $currentUser->hasRole(RoleName::adminRoles());
            $isDeptAdmin = $isAdmin || $currentUser->hasRole('dept_admin') || $currentUser->hasRole('Department Administrator');
            if (! $isAdmin && ! $isDeptAdmin && ! $currentUser->can('user.view')) {
                abort(403, 'Unauthorized to view user permissions.');
            }
        }

        $details = $this->roleService->getUserPermissions($user);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department,
            ],
            'roles' => $details['roles'],
            'permissions' => $details['permissions'],
            'details' => $details,
        ]);
    }
}
