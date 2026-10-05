<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountKind;
use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Cash',
                'type' => AccountKind::CASH,
                'opening_balance' => '0.00',
                'is_active' => true,
            ],
            [
                'name' => 'bKash',
                'type' => AccountKind::MOBILE_WALLET,
                'opening_balance' => '0.00',
                'is_active' => true,
            ],
            [
                'name' => 'Nagad',
                'type' => AccountKind::MOBILE_WALLET,
                'opening_balance' => '0.00',
                'is_active' => true,
            ],
            [
                'name' => 'Bank Account',
                'type' => AccountKind::BANK,
                'opening_balance' => '0.00',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            Account::firstOrCreate(['name' => $acc['name']], $acc);
        }
    }
}
