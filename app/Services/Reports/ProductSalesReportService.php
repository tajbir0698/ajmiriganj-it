<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductSalesReportService
{
    /**
     * Generate product sales & profit report.
     *
     * @param  array{category_id?: ?int, product_id?: ?int}  $filters
     * @return array{
     *     period: array{from: ?string, to: ?string},
     *     rows: Collection<int, array<string, mixed>>,
     *     categories: Collection<string, mixed>,
     *     summary: array<string, mixed>,
     * }
     */
    public function generate(ReportPeriod $period, array $filters = []): array
    {
        // 1. Fetch completed sale items in period
        $saleItemsQuery = SaleItem::query()
            ->whereHas('sale', function ($q) use ($period): void {
                $q->where('status', SaleStatus::COMPLETED);
                if ($period->from) {
                    $q->whereDate('sale_date', '>=', $period->fromDateString());
                }
                if ($period->to) {
                    $q->whereDate('sale_date', '<=', $period->toDateString());
                }
            })
            ->with(['product.category', 'product.unit', 'sale']);

        if (! empty($filters['product_id'])) {
            $saleItemsQuery->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['category_id'])) {
            $saleItemsQuery->whereHas('product', function ($q) use ($filters): void {
                $q->where('category_id', $filters['category_id']);
            });
        }

        $saleItems = $saleItemsQuery->get();

        // 2. Fetch sale return items in period
        $returnItemsQuery = SaleReturnItem::query()
            ->whereHas('saleReturn', function ($q) use ($period): void {
                if ($period->from) {
                    $q->whereDate('return_date', '>=', $period->fromDateString());
                }
                if ($period->to) {
                    $q->whereDate('return_date', '<=', $period->toDateString());
                }
            })
            ->with(['saleReturn', 'saleItem']);

        if (! empty($filters['product_id'])) {
            $returnItemsQuery->where('product_id', $filters['product_id']);
        }

        $returnItems = $returnItemsQuery->get()->groupBy('product_id');

        // Group sale items by product
        $itemsByProduct = $saleItems->groupBy('product_id');
        $allProductIds = $itemsByProduct->keys()->merge($returnItems->keys())->unique()->all();

        $rows = new Collection();
        $totalSoldQty = '0.000';
        $totalReturnedQty = '0.000';
        $totalRevenue = '0.00';
        $totalCost = '0.00';
        $totalLineProfit = '0.00';
        $totalProfitReversed = '0.00';
        $totalNetProfitAfterReturns = '0.00';

        foreach ($allProductIds as $pid) {
            $pItems = $itemsByProduct->get($pid, new Collection());
            $pRts = $returnItems->get($pid, new Collection());

            /** @var Product|null $product */
            $product = $pItems->first()?->product ?? Product::with(['category', 'unit'])->find($pid);
            if (! $product) {
                continue;
            }

            $soldQty = '0.000';
            $revenue = '0.00';
            $cost = '0.00';
            $lineProfit = '0.00';

            foreach ($pItems as $si) {
                $soldQty = bcadd($soldQty, (string) $si->qty, 3);
                $revenue = bcadd($revenue, (string) $si->line_total, 2);
                $cost = bcadd($cost, (string) $si->line_cost, 2);
                $lineProfit = bcadd($lineProfit, (string) $si->line_profit, 2);
            }

            $retQty = '0.000';
            $retProfitReversed = '0.00';

            foreach ($pRts as $ri) {
                $retQty = bcadd($retQty, (string) $ri->qty, 3);
                $profitRev = bcsub((string) $ri->line_refund, (string) $ri->line_cost_restored, 2);
                $retProfitReversed = bcadd($retProfitReversed, $profitRev, 2);
            }

            $netQty = bcsub($soldQty, $retQty, 3);
            $profitAfterReturns = bcsub($lineProfit, $retProfitReversed, 2);

            $margin = '0.00';
            if (bccomp($revenue, '0.00', 2) > 0) {
                $margin = bcdiv(bcmul($profitAfterReturns, '100.00', 4), $revenue, 2);
            }

            $rows->push([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_name' => $product->category?->name ?? 'Uncategorized',
                'unit' => $product->unit?->name ?? '',
                'qty_sold' => $soldQty,
                'sold_qty' => $soldQty,
                'qty_returned' => $retQty,
                'returned_qty' => $retQty,
                'net_qty' => $netQty,
                'revenue' => $revenue,
                'cost' => $cost,
                'line_profit' => $lineProfit,
                'profit_reversed' => $retProfitReversed,
                'profit_after_returns' => $profitAfterReturns,
                'margin_percent' => $margin.'%',
            ]);

            $totalSoldQty = bcadd($totalSoldQty, $soldQty, 3);
            $totalReturnedQty = bcadd($totalReturnedQty, $retQty, 3);
            $totalRevenue = bcadd($totalRevenue, $revenue, 2);
            $totalCost = bcadd($totalCost, $cost, 2);
            $totalLineProfit = bcadd($totalLineProfit, $lineProfit, 2);
            $totalProfitReversed = bcadd($totalProfitReversed, $retProfitReversed, 2);
            $totalNetProfitAfterReturns = bcadd($totalNetProfitAfterReturns, $profitAfterReturns, 2);
        }

        // Bill discounts in period to reconcile to Profit & Loss Gross Profit
        $billDiscountsQuery = Sale::query()->where('status', SaleStatus::COMPLETED);
        if ($period->from) {
            $billDiscountsQuery->whereDate('sale_date', '>=', $period->fromDateString());
        }
        if ($period->to) {
            $billDiscountsQuery->whereDate('sale_date', '<=', $period->toDateString());
        }
        $totalBillDiscounts = (string) $billDiscountsQuery->sum('discount');

        $reconciledGrossProfit = bcsub($totalNetProfitAfterReturns, $totalBillDiscounts, 2);

        // Group by category for subtotal analysis
        $categories = $rows->groupBy('category_name')->map(function ($catRows) {
            $catRevenue = '0.00';
            $catProfit = '0.00';
            $catQty = '0.000';
            foreach ($catRows as $r) {
                $catRevenue = bcadd($catRevenue, $r['revenue'], 2);
                $catProfit = bcadd($catProfit, $r['profit_after_returns'], 2);
                $catQty = bcadd($catQty, $r['net_qty'], 3);
            }

            return [
                'count' => count($catRows),
                'qty' => $catQty,
                'revenue' => $catRevenue,
                'profit' => $catProfit,
            ];
        });

        return [
            'period' => [
                'from' => $period->fromDateString(),
                'to' => $period->toDateString(),
            ],
            'rows' => $rows->sortByDesc(fn ($r) => (float) $r['revenue'])->values(),
            'categories' => $categories,
            'summary' => [
                'total_sold_qty' => $totalSoldQty,
                'total_returned_qty' => $totalReturnedQty,
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_line_profit' => $totalLineProfit,
                'total_profit_reversed' => $totalProfitReversed,
                'profit_after_returns' => $totalNetProfitAfterReturns,
                'less_bill_discounts' => $totalBillDiscounts,
                'reconciled_gross_profit' => $reconciledGrossProfit,
            ],
        ];
    }
}
