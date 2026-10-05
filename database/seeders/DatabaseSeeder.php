<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
            SettingSeeder::class,
            AccountCategorySeeder::class,
            AccountSeeder::class,
            VendorSeeder::class,
            CategoryAndProductSeeder::class,
        ]);
    }
}
