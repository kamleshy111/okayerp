<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the new permissions
        $permissions = [
            'income category manage',
            'income manage'
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        // Assign to 'store' role if it exists
        $storeRole = Role::where('name', 'store')->first();
        if ($storeRole) {
            $storeRole->givePermissionTo($permissions);
        }

        // Direct assign to all existing users with 'store' role
        $storeUsers = User::where('role', 'store')->get();
        foreach ($storeUsers as $user) {
            $user->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete the permissions
        Permission::whereIn('name', [
            'income category manage',
            'income manage'
        ])->delete();
    }
};
