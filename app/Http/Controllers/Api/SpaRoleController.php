<?php

namespace App\Http\Controllers\Api;

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
     * List all system and custom roles with user counts and permissions.
     */
    public function index(): JsonResponse
    {
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
        $user = User::where('public_id', $id)->orWhere('id', $id)->with('department')->firstOrFail();
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
