<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Seed/Update Permissions
        $permissions = [
            'supplier manage',
            'category manage',
            'product manage',
            'sale manage',
            'purchase manage',
            'customer manage',
            'payment supplier manage',
            'payments customer manage',
            'expense category manage',
            'expense manage'
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        // Seed/Update Roles
        $adminRole = Role::findOrCreate('admin');
        $storeRole = Role::findOrCreate('store');

        // Assign Permissions to Roles
        $adminRole->syncPermissions([]);
       // $storeRole->syncPermissions(['supplier manage', 'category manage', 'product manage', 'sale manage', 'purchase manage', 'customer manage', 'payment supplier manage', 'payments customer manage', 'expense category manage', 'expense manage']);

        // Seed Admin User
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '1234567890',
            ]
        );
        $adminUser->assignRole($adminRole);

       
       
    }
}
