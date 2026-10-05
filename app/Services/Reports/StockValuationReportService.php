<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Services\FifoStockService;
use Illuminate\Support\Collection;

class StockValuationReportService
{
    public function __construct(
        protected FifoStockService $fifoStockService
    ) {}

    /**
     * @param  array{
     *     category_id?: ?int,
     *     stock_status?: ?string
     * }  $filters
     * @return array{
     *     categories: Collection<int, array{
     *         category_id: int,
     *         category_name: string,
     *         products: Collection<int, array{
     *             product_id: int,
     *             sku: string,
     *             name: string,
     *             unit: string,
     *             stock_qty: string,
     *             fifo_stock_value: string,
     *             avg_cost: string,
     *             last_cost: string,
     *             sale_price: string,
     *             potential_retail_value: string,
     *             potential_profit: string,
     *             margin_percent: string
     *         }>,
     *         total_qty: string,
     *         total_fifo_value: string,
     *         total_retail_value: string,
     *         total_potential_profit: string
     *     }>,
     *     totals: array{
     *         total_qty: string,
     *         total_fifo_value: string,
     *         total_retail_value: string,
     *         total_potential_profit: string,
     *         margin_percent: string,
     *         product_count: int
     *     }
     * }
     */
    public function generate(array $filters = []): array
    {
        $productQuery = Product::query()
            ->with(['category', 'unit'])
            ->orderBy('category_id')
            ->orderBy('name');

        if (! empty($filters['category_id'])) {
            $productQuery->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['stock_status'])) {
            if ($filters['stock_status'] === 'low_stock') {
                $productQuery->lowStock();
            } elseif ($filters['stock_status'] === 'out_of_stock') {
                $productQuery->outOfStock();
            } elseif ($filters['stock_status'] === 'in_stock') {
                $productQuery->where('stock_qty', '>', 0);
            }
        }

        $products = $productQuery->get();

        // Preload active batches grouped by product_id to avoid N+1
        $activeBatches = PurchaseItem::query()
            ->where('remaining_qty', '>', 0)
            ->get(['product_id', 'remaining_qty', 'landed_unit_cost'])
            ->groupBy('product_id');

        $byCategory = $products->groupBy('category_id');

        $resultCategories = collect();
        $overallQty = '0.000';
        $overallFifoValue = '0.00';
        $overallRetailValue = '0.00';
        $overallProfit = '0.00';
        $totalProductsCount = 0;

        foreach ($byCategory as $catId => $catProducts) {
            /** @var Category|null $category */
            $category = $catProducts->first()?->category;
            $catName = $category ? $category->name : 'Uncategorized';

            $catQty = '0.000';
            $catFifoValue = '0.00';
            $catRetailValue = '0.00';
            $catProfit = '0.00';
            $catProductRows = collect();

            foreach ($catProducts as $product) {
                $stockQty = bcadd((string) $product->stock_qty, '0.000', 3);
                $salePrice = bcadd((string) $product->sale_price, '0.00', 2);
                $lastCost = bcadd((string) $product->last_cost, '0.00', 2);

                // Compute product FIFO value from batches
                $prodBatches = $activeBatches->get($product->id) ?? collect();
                $prodFifoVal4 = '0.0000';
                foreach ($prodBatches as $batch) {
                    $prodFifoVal4 = bcadd($prodFifoVal4, bcmul((string) $batch->remaining_qty, (string) $batch->landed_unit_cost, 4), 4);
                }
                $prodFifoValue = bcadd($prodFifoVal4, '0.00', 2);

                // Avg cost = fifo_stock_value / stock_qty (if stock_qty > 0)
                $avgCost = bccomp($stockQty, '0.000', 3) > 0
                    ? bcdiv($prodFifoValue, $stockQty, 2)
                    : $lastCost;

                // Potential retail value = stock_qty * sale_price
                $retailVal = bcmul($stockQty, $salePrice, 2);
                $potentialProfit = bcsub($retailVal, $prodFifoValue, 2);

                $marginPct = '0.00';
                if (bccomp($retailVal, '0.00', 2) > 0) {
                    $marginPct = bcdiv(bcmul($potentialProfit, '100.00', 4), $retailVal, 2);
                }

                $catProductRows->push([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit' => $product->unit ? $product->unit->name : '',
                    'stock_qty' => $stockQty,
                    'fifo_stock_value' => $prodFifoValue,
                    'avg_cost' => $avgCost,
                    'last_cost' => $lastCost,
                    'sale_price' => $salePrice,
                    'potential_retail_value' => $retailVal,
                    'potential_profit' => $potentialProfit,
                    'margin_percent' => $marginPct,
                ]);

                $catQty = bcadd($catQty, $stockQty, 3);
                $catFifoValue = bcadd($catFifoValue, $prodFifoValue, 2);
                $catRetailValue = bcadd($catRetailValue, $retailVal, 2);
                $catProfit = bcadd($catProfit, $potentialProfit, 2);
                $totalProductsCount++;
            }

            $resultCategories->push([
                'category_id' => $catId ?: 0,
                'category_name' => $catName,
                'products' => $catProductRows,
                'total_qty' => $catQty,
                'total_fifo_value' => $catFifoValue,
                'total_retail_value' => $catRetailValue,
                'total_potential_profit' => $catProfit,
            ]);

            $overallQty = bcadd($overallQty, $catQty, 3);
            $overallFifoValue = bcadd($overallFifoValue, $catFifoValue, 2);
            $overallRetailValue = bcadd($overallRetailValue, $catRetailValue, 2);
            $overallProfit = bcadd($overallProfit, $catProfit, 2);
        }

        $overallMargin = '0.00';
        if (bccomp($overallRetailValue, '0.00', 2) > 0) {
            $overallMargin = bcdiv(bcmul($overallProfit, '100.00', 4), $overallRetailValue, 2);
        }

        return [
            'categories' => $resultCategories,
            'totals' => [
                'total_qty' => $overallQty,
                'total_fifo_value' => $overallFifoValue,
                'total_retail_value' => $overallRetailValue,
                'total_potential_profit' => $overallProfit,
                'margin_percent' => $overallMargin,
                'product_count' => $totalProductsCount,
            ],
        ];
    }
}
