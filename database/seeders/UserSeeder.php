<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Super Admin (Owner)
        $admin = User::firstOrCreate(
            ['email' => 'admin@ajmiriganj.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([RoleName::SUPER_ADMIN->value]);

        // 2. Manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@ajmiriganj.com'],
            [
                'name' => 'Shop Manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $manager->syncRoles([RoleName::MANAGER->value]);
    }
}
