<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get()->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ];
        });

        return Inertia::render('Admin/Role/Index', [
            'roles' => $roles
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        $permissions = Permission::all(['id', 'name']);

        return Inertia::render('Admin/Role/Create', [
            'permissions' => $permissions
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array'
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->filled('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role created successfully!',
            'role' => $role
        ], 201);
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit($id)
    {
        $role = Role::findById($id);
        $rolePermissions = $role->permissions->pluck('name');
        $permissions = Permission::all(['id', 'name']);

        return Inertia::render('Admin/Role/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'rolePermissions' => $rolePermissions,
            'permissions' => $permissions
        ]);
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findById($id);

        if (in_array($role->name, ['admin', 'store'])) {
            $request->validate([
                'permissions' => 'nullable|array'
            ]);
        } else {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $id,
                'permissions' => 'nullable|array'
            ]);
            $role->name = $request->name;
            $role->save();
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions ?? []);
        }

        return response()->json([
            'message' => 'Role updated successfully!',
            'role' => $role
        ]);
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy($id)
    {
        $role = Role::findById($id);

        if (in_array($role->name, ['admin', 'store'])) {
            return response()->json(['message' => 'Critical system roles cannot be deleted.'], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully!'
        ]);
    }

    /**
     * Display a listing of the permissions.
     */
    public function permissionIndex()
    {
        $permissions = Permission::all(['id', 'name']);

        return Inertia::render('Admin/Permission/Index', [
            'permissions' => $permissions
        ]);
    }

    /**
     * Store a newly created permission in storage.
     */
    public function permissionStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name'
        ]);

        $permission = Permission::create(['name' => $request->name]);

        return response()->json([
            'message' => 'Permission created successfully!',
            'permission' => $permission
        ], 201);
    }

    /**
     * Update the specified permission in storage.
     */
    public function permissionUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $id
        ]);

        $permission = Permission::findById($id);
        $permission->name = $request->name;
        $permission->save();

        return response()->json([
            'message' => 'Permission updated successfully!',
            'permission' => $permission
        ]);
    }

    /**
     * Remove the specified permission from storage.
     */
    public function permissionDestroy($id)
    {
        $permission = Permission::findById($id);
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully!'
        ]);
    }
}
