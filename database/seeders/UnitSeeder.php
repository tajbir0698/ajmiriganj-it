<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Piece', 'short_name' => 'Pcs'],
            ['name' => 'Kilogram', 'short_name' => 'Kg'],
            ['name' => 'Box', 'short_name' => 'Box'],
            ['name' => 'Dozen', 'short_name' => 'Dzn'],
            ['name' => 'Liter', 'short_name' => 'Ltr'],
            ['name' => 'Packet', 'short_name' => 'Pkt'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['name' => $unit['name']], $unit);
        }
    }
}
