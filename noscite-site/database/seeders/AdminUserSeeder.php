<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@noscite.it'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin@Noscite2024!'),
            ]
        );

        $admin->assignRole('admin');
    }
}
