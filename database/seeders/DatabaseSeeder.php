<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Admin User
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '1234567890',
            ]
        );

        // Seed Store User
        // User::updateOrCreate(
        //     ['email' => 'mukesh@gmail.com'],
        //     [
        //         'name' => 'Mukesh Store',
        //         'password' => Hash::make('password'),
        //         'role' => 'store',
        //         'phone' => '9876543210',
        //     ]
        // );
    }
}
