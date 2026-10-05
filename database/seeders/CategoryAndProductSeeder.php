<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BatchSource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\FifoStockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryAndProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fifoStockService = app(FifoStockService::class);

        $catAccessories = Category::firstOrCreate(['name' => 'Computer Accessories']);
        $catNetworking = Category::firstOrCreate(['name' => 'Networking Devices']);
        $catStorage = Category::firstOrCreate(['name' => 'Storage & SSD']);
        $catPeripherals = Category::firstOrCreate(['name' => 'Peripherals & Audio']);

        $pcs = Unit::where('name', 'Piece')->first() ?? Unit::create(['name' => 'Piece', 'short_name' => 'Pcs']);

        $products = [
            [
                'sku' => 'MOUSE-LOG-B100',
                'barcode' => '8901234567890',
                'name' => 'Logitech B100 USB Optical Mouse',
                'category_id' => $catAccessories->id,
                'unit_id' => $pcs->id,
                'brand' => 'Logitech',
                'description' => 'Reliable optical wired mouse for desktop and office setup.',
                'last_cost' => '280.0000',
                'sale_price' => '380.00',
                'stock_qty' => '25.000',
                'alert_qty' => '5.000',
                'is_active' => true,
            ],
            [
                'sku' => 'KB-A4T-FK10',
                'barcode' => '8901234567891',
                'name' => 'A4Tech FK10 ComfortKey Keyboard',
                'category_id' => $catAccessories->id,
                'unit_id' => $pcs->id,
                'brand' => 'A4Tech',
                'description' => 'Sleek comfort USB keyboard with drain holes.',
                'last_cost' => '550.0000',
                'sale_price' => '750.00',
                'stock_qty' => '15.000',
                'alert_qty' => '3.000',
                'is_active' => true,
            ],
            [
                'sku' => 'ROUT-TPL-840N',
                'barcode' => '8901234567892',
                'name' => 'TP-Link TL-WR840N 300Mbps Router',
                'category_id' => $catNetworking->id,
                'unit_id' => $pcs->id,
                'brand' => 'TP-Link',
                'description' => '300Mbps Wireless N Speed router with 2 omni antennas.',
                'last_cost' => '1150.0000',
                'sale_price' => '1450.00',
                'stock_qty' => '10.000',
                'alert_qty' => '3.000',
                'is_active' => true,
            ],
            [
                'sku' => 'USB-SND-64G',
                'barcode' => '8901234567893',
                'name' => 'SanDisk Ultra 64GB USB 3.0 Flash Drive',
                'category_id' => $catStorage->id,
                'unit_id' => $pcs->id,
                'brand' => 'SanDisk',
                'description' => 'High speed transfer USB 3.0 pen drive.',
                'last_cost' => '480.0000',
                'sale_price' => '650.00',
                'stock_qty' => '30.000',
                'alert_qty' => '5.000',
                'is_active' => true,
            ],
            [
                'sku' => 'CAB-CAT6-1M',
                'barcode' => '8901234567894',
                'name' => 'Cat6 High-Speed Network Cable 1M',
                'category_id' => $catNetworking->id,
                'unit_id' => $pcs->id,
                'brand' => 'D-Link',
                'description' => 'Molded RJ45 Gigabit patch cord.',
                'last_cost' => '40.0000',
                'sale_price' => '80.00',
                'stock_qty' => '50.000',
                'alert_qty' => '10.000',
                'is_active' => true,
            ],
            [
                'sku' => 'SSD-WDG-240',
                'barcode' => '8901234567895',
                'name' => 'Western Digital Green 240GB SATA SSD',
                'category_id' => $catStorage->id,
                'unit_id' => $pcs->id,
                'brand' => 'WD',
                'description' => 'Fast 2.5 inch internal solid state drive.',
                'last_cost' => '1850.0000',
                'sale_price' => '2300.00',
                'stock_qty' => '8.000',
                'alert_qty' => '2.000',
                'is_active' => true,
            ],
            [
                'sku' => 'HEAD-HAV-2105',
                'barcode' => '8901234567896',
                'name' => 'Havit HV-H2105D Stereo Headphone',
                'category_id' => $catPeripherals->id,
                'unit_id' => $pcs->id,
                'brand' => 'Havit',
                'description' => 'Over-ear multimedia headphone with microphone.',
                'last_cost' => '380.0000',
                'sale_price' => '550.00',
                'stock_qty' => '12.000',
                'alert_qty' => '3.000',
                'is_active' => true,
            ],
        ];

        DB::transaction(function () use ($products, $fifoStockService): void {
            foreach ($products as $data) {
                $initialStock = $data['stock_qty'];
                $initialCost = $data['last_cost'];
                $data['stock_qty'] = '0.000'; // initialize at 0, then add opening batch

                $product = Product::firstOrCreate(['sku' => $data['sku']], $data);

                if (bccomp($initialStock, '0.000', 3) > 0 && $product->batches()->count() === 0) {
                    $fifoStockService->addBatch(
                        product: $product,
                        qty: $initialStock,
                        unitCost: $initialCost,
                        source: BatchSource::OPENING,
                        batchDate: now()->subDays(10),
                    );
                }
            }
        });
    }
}
