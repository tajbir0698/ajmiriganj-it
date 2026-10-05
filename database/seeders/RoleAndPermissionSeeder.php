<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Seed exactly two roles
        Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleName::MANAGER->value, 'guard_name' => 'web']);
    }
}
