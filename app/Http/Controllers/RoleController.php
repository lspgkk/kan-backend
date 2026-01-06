<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
// use App\Models\AuditLog;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(Request $request)
    {
        $query = Role::with('permissions');

        // Search filter (by role name, you can add more fields if needed)
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Get per_page from request, default to 20
        $perPage = $request->input('per_page', 20);

        // Paginate the result
        $roles = $query->paginate($perPage);

        return response()->json([
            'message' => 'Roles retrieved successfully',
            'data' => $roles
        ]);
    }

    /**
     * Store a newly created role with permissions.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array|nullable'
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'api',
            ]);

            // AuditLog::create([
            //     'user_id' => auth()->id(),
            //     'description' => "Menambahkan role $role->name"
            // ]);

            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();
            return response()->json(['message' => 'Role created successfully', 'data' => $role], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified role with permissions.
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json([
            'message' => 'Role retrieved successfully',
            'data' => $role
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|unique:roles,name,' . $id,
            'permissions' => 'array|nullable'
        ]);

        DB::beginTransaction();
        try {
            if ($request->filled('name')) {
                $role->update(['name' => $request->name]);
            }

            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            // AuditLog::create([
            //     'user_id' => auth()->id(),
            //     'description' => "Mengubah role $role->name"
            // ]);

            DB::commit();
            return response()->json(['message' => 'Role updated successfully', 'data' => $role]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'sroc' || $role->name === 'frt') {
            return response()->json(['error' => "Cannot delete $role->name role"], 403);
        }

        DB::beginTransaction();
        try {
            // AuditLog::create([
            //     'user_id' => auth()->id(),
            //     'description' => "Menghapus role $role->name"
            // ]);

            // Remove all assigned users
            $role->users()->detach(); // <-- important

            // Delete the role itself
            $role->delete();

            DB::commit();
            return response()->json(['message' => 'Role deleted successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all available permissions.
     */
    public function permissions()
    {
        $permissions = Permission::all();
        return response()->json([
            'message' => 'Permissions retrieved successfully', 
            'data' => $permissions
        ]);
    }

    /**
     * Assign permissions to a specific role.
     */
    public function assignPermissions(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $request->validate([
            'permissions' => 'required|array'
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permissions updated successfully',
            'role' => $role->load('permissions')
        ]);
    }
}
