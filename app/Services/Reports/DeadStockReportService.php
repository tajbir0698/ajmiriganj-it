<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DeadStockReportService
{
    /**
     * @param  array{
     *     days?: ?int,
     *     category_id?: ?int
     * }  $filters
     * @return array{
     *     days_threshold: int,
     *     rows: Collection<int, array{
     *         product_id: int,
     *         sku: string,
     *         name: string,
     *         category_name: string,
     *         unit: string,
     *         stock_qty: string,
     *         fifo_stock_value: string,
     *         last_sale_date: ?string,
     *         days_since_sale: string
     *     }>,
     *     totals: array{
     *         total_qty: string,
     *         total_fifo_value: string,
     *         count: int
     *     }
     * }
     */
    public function generate(array $filters = []): array
    {
        $defaultDays = (int) Setting::get('dead_stock_days', 60);
        $daysThreshold = ! empty($filters['days']) && (int) $filters['days'] > 0
            ? (int) $filters['days']
            : $defaultDays;

        $cutoffDate = now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->subDays($daysThreshold)->toDateString();

        $productQuery = Product::query()
            ->with(['category', 'unit'])
            ->where('stock_qty', '>', 0)
            ->orderBy('name');

        if (! empty($filters['category_id'])) {
            $productQuery->where('category_id', $filters['category_id']);
        }

        $products = $productQuery->get();

        // Preload active batches grouped by product_id for valuation
        $activeBatches = PurchaseItem::query()
            ->where('remaining_qty', '>', 0)
            ->get(['product_id', 'remaining_qty', 'landed_unit_cost'])
            ->groupBy('product_id');

        // Preload latest completed sale_date for all products
        $latestSales = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', SaleStatus::COMPLETED)
            ->selectRaw('sale_items.product_id, MAX(sales.sale_date) as last_sale_date')
            ->groupBy('sale_items.product_id')
            ->pluck('last_sale_date', 'product_id');

        $rows = collect();
        $totalQty = '0.000';
        $totalFifoValue = '0.00';
        $today = now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->startOfDay();

        foreach ($products as $product) {
            $lastSaleDate = $latestSales->get($product->id);

            // If sold after cutoff, not dead stock
            if ($lastSaleDate !== null && Carbon::parse($lastSaleDate)->toDateString() > $cutoffDate) {
                continue;
            }

            $daysSinceStr = 'Never Sold';
            if ($lastSaleDate !== null) {
                $days = Carbon::parse($lastSaleDate)->startOfDay()->diffInDays($today);
                $daysSinceStr = (string) $days;
            }

            $stockQty = bcadd((string) $product->stock_qty, '0.000', 3);

            $prodBatches = $activeBatches->get($product->id) ?? collect();
            $prodFifoVal4 = '0.0000';
            foreach ($prodBatches as $batch) {
                $prodFifoVal4 = bcadd($prodFifoVal4, bcmul((string) $batch->remaining_qty, (string) $batch->landed_unit_cost, 4), 4);
            }
            $prodFifoValue = bcadd($prodFifoVal4, '0.00', 2);

            $rows->push([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category_name' => $product->category ? $product->category->name : 'Uncategorized',
                'unit' => $product->unit ? $product->unit->name : '',
                'stock_qty' => $stockQty,
                'fifo_stock_value' => $prodFifoValue,
                'last_sale_date' => $lastSaleDate ? Carbon::parse($lastSaleDate)->toDateString() : null,
                'days_since_sale' => $daysSinceStr,
            ]);

            $totalQty = bcadd($totalQty, $stockQty, 3);
            $totalFifoValue = bcadd($totalFifoValue, $prodFifoValue, 2);
        }

        return [
            'days_threshold' => $daysThreshold,
            'rows' => $rows,
            'totals' => [
                'total_qty' => $totalQty,
                'total_fifo_value' => $totalFifoValue,
                'count' => $rows->count(),
            ],
        ];
    }
}
