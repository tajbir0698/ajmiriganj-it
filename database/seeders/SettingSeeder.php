<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'manager_sales_visibility',
                'value' => 'all',
                'type' => 'string',
                'description' => 'Manager sales visibility mode: "all" (all completed sales) or "own" (only sales created by that manager)',
            ],
            [
                'key' => 'manager_can_add_products',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Allow Manager role to submit new products (subject to review)',
            ],
            [
                'key' => 'manager_can_request_restock',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Allow Manager role to submit restock requests to Super Admin',
            ],
            [
                'key' => 'credit_sales_enabled',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Allow credit / due sales with registered customers',
            ],
            [
                'key' => 'site_title',
                'value' => 'Ajmiriganj IT',
                'type' => 'string',
                'description' => 'Browser tab title and system branding title',
            ],
            [
                'key' => 'favicon',
                'value' => null,
                'type' => 'string',
                'description' => 'Custom browser favicon icon image (PNG, ICO, SVG)',
            ],
            [
                'key' => 'shop_name',
                'value' => 'Ajmiriganj IT',
                'type' => 'string',
                'description' => 'Shop business display name',
            ],
            [
                'key' => 'shop_address',
                'value' => 'Main Bazar, Ajmiriganj, Habiganj, Sylhet',
                'type' => 'string',
                'description' => 'Physical shop address',
            ],
            [
                'key' => 'shop_phone',
                'value' => '+8801712345678',
                'type' => 'string',
                'description' => 'Primary shop contact number',
            ],
            [
                'key' => 'receipt_footer',
                'value' => 'Thank you for shopping with us',
                'type' => 'string',
                'description' => 'Printed footer note on thermal receipt',
            ],
            [
                'key' => 'receipt_width_mm',
                'value' => '80',
                'type' => 'integer',
                'description' => 'Thermal receipt width in mm (80 or 58)',
            ],
            [
                'key' => 'auto_print_receipt',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Automatically trigger print receipt dialog after completed sale',
            ],
            [
                'key' => 'dead_stock_days',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Threshold in days to classify non-moving inventory as dead stock',
            ],
            [
                'key' => 'payment_account_cash',
                'value' => '1',
                'type' => 'integer',
                'description' => 'Default financial account for Cash payments',
            ],
            [
                'key' => 'payment_account_bkash',
                'value' => '2',
                'type' => 'integer',
                'description' => 'Default financial account for bKash payments',
            ],
            [
                'key' => 'payment_account_nagad',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Default financial account for Nagad payments',
            ],
            [
                'key' => 'payment_account_bank',
                'value' => '4',
                'type' => 'integer',
                'description' => 'Default financial account for Bank payments',
            ],
            [
                'key' => 'low_stock_threshold',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Global default threshold for low stock alert',
            ],
            [
                'key' => 'currency_symbol',
                'value' => '৳',
                'type' => 'string',
                'description' => 'Display currency symbol',
            ],
            [
                'key' => 'default_target_margin_percent',
                'value' => '25',
                'type' => 'float',
                'description' => 'Default profit margin % for suggested sale price',
            ],
            [
                'key' => 'round_suggested_price_to',
                'value' => '1',
                'type' => 'integer',
                'description' => 'Round suggested price to nearest whole taka (1)',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
