<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = [
            [
                'name' => 'Star Tech Distribution',
                'contact_person' => 'Md. Rafiqul Islam',
                'phone' => '+8801811111111',
                'alt_phone' => '+8801711111112',
                'email' => 'rafiq@startech-dist.com',
                'address' => 'Multiplan Center, Level 9, Elephant Road, Dhaka',
                'opening_balance' => '0.00',
                'note' => 'Main distributor for Logitech and TP-Link products',
                'is_active' => true,
            ],
            [
                'name' => 'Smart Technologies BD Ltd',
                'contact_person' => 'Tanvir Ahmed',
                'phone' => '+8801822222222',
                'alt_phone' => null,
                'email' => 'tanvir@smart-bd.com',
                'address' => 'BCS Computer City, IDB Bhaban, Agargaon, Dhaka',
                'opening_balance' => '1500.00',
                'note' => 'Distributor for WD SSD and A4Tech accessories. Opening balance from previous manual register.',
                'is_active' => true,
            ],
        ];

        foreach ($vendors as $v) {
            Vendor::firstOrCreate(['name' => $v['name']], $v);
        }
    }
}
