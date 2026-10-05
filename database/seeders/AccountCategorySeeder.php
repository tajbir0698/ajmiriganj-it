<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountCategoryType;
use App\Models\AccountCategory;
use Illuminate\Database\Seeder;

class AccountCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // System categories (affects_profit = false)
            ['name' => 'Sales Income', 'type' => AccountCategoryType::INCOME, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Due Collection', 'type' => AccountCategoryType::INCOME, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Vendor Payment', 'type' => AccountCategoryType::EXPENSE, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Owner Investment', 'type' => AccountCategoryType::EQUITY, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Owner Drawing', 'type' => AccountCategoryType::EQUITY, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Profit Withdrawal', 'type' => AccountCategoryType::EQUITY, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Transfer Out', 'type' => AccountCategoryType::TRANSFER, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Transfer In', 'type' => AccountCategoryType::TRANSFER, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Vendor Refund', 'type' => AccountCategoryType::INCOME, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Sales Refund', 'type' => AccountCategoryType::EXPENSE, 'is_system' => true, 'is_active' => true, 'affects_profit' => false],

            // Editable categories (affects_profit = true for operational expenses & other income)
            ['name' => 'Rent', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Salary', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Electricity', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Transport', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Internet/Phone', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Marketing', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Repairs', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Other Expense', 'type' => AccountCategoryType::EXPENSE, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Other Income', 'type' => AccountCategoryType::INCOME, 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
        ];

        foreach ($categories as $cat) {
            AccountCategory::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
