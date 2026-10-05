<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    /**
     * Generate sales report.
     *
     * @param  array{
     *     cashier_id?: ?int,
     *     customer_id?: ?int,
     *     payment_method?: ?string,
     *     product_id?: ?int,
     *     category_id?: ?int,
     *     group_by?: string,
     * }  $filters
     * @return array{
     *     period: array{from: ?string, to: ?string},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>,
     *     is_manager: bool,
     * }
     */
    public function generate(ReportPeriod $period, array $filters = [], ?User $user = null): array
    {
        $isManager = $user ? ! $user->isSuperAdmin() : false;
        $visibility = \App\Models\Setting::get('manager_sales_visibility', 'all');
        $cashierId = ($isManager && $visibility === 'own' && $user) ? $user->id : ($filters['cashier_id'] ?? null);

        $salesQuery = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->with(['customer', 'creator', 'items.product.category', 'returns']);

        if ($period->from) {
            $salesQuery->whereDate('sale_date', '>=', $period->fromDateString());
        }
        if ($period->to) {
            $salesQuery->whereDate('sale_date', '<=', $period->toDateString());
        }

        if ($cashierId) {
            $salesQuery->where('created_by', $cashierId);
        }

        if (! empty($filters['customer_id'])) {
            $salesQuery->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['payment_method'])) {
            $salesQuery->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['product_id'])) {
            $salesQuery->whereHas('items', function ($q) use ($filters): void {
                $q->where('product_id', $filters['product_id']);
            });
        }

        if (! empty($filters['category_id'])) {
            $salesQuery->whereHas('items.product', function ($q) use ($filters): void {
                $q->where('category_id', $filters['category_id']);
            });
        }

        $sales = $salesQuery->orderBy('sale_date', 'desc')->orderBy('id', 'desc')->get();

        // Calculate sale return sums in this period
        $saleIds = $sales->pluck('id')->all();
        $returnsQuery = SaleReturn::query();
        if (! empty($saleIds)) {
            $returnsQuery->whereIn('sale_id', $saleIds);
        } else {
            $returnsQuery->whereRaw('1 = 0');
        }
        if ($period->from) {
            $returnsQuery->whereDate('return_date', '>=', $period->fromDateString());
        }
        if ($period->to) {
            $returnsQuery->whereDate('return_date', '<=', $period->toDateString());
        }
        $returnsBySale = $returnsQuery->get()->groupBy('sale_id');

        $rows = new Collection();
        $totalSubtotal = '0.00';
        $totalDiscount = '0.00';
        $totalSales = '0.00';
        $totalPaid = '0.00';
        $totalDue = '0.00';
        $totalReturnsRefund = '0.00';
        $totalCostRestored = '0.00';
        $totalProfitReversed = '0.00';
        $totalCogs = '0.00';
        $totalNetProfit = '0.00';

        foreach ($sales as $sale) {
            $saleReturns = $returnsBySale->get($sale->id, new Collection());
            $saleReturnRefund = '0.00';
            $saleCostRestored = '0.00';
            $saleProfitReversed = '0.00';

            foreach ($saleReturns as $ret) {
                $saleReturnRefund = bcadd($saleReturnRefund, (string) $ret->refund_amount, 2);
                $saleCostRestored = bcadd($saleCostRestored, (string) $ret->cost_restored, 2);
                $saleProfitReversed = bcadd($saleProfitReversed, (string) $ret->profit_reversed, 2);
            }

            // Sale line cost
            $saleGrossCost = '0.00';
            foreach ($sale->items as $item) {
                $saleGrossCost = bcadd($saleGrossCost, (string) $item->line_cost, 2);
            }

            $effectiveCogs = bcsub($saleGrossCost, $saleCostRestored, 2);
            $effectiveProfit = bcsub((string) $sale->net_profit, $saleProfitReversed, 2);
            $netSaleAmount = bcsub((string) $sale->total, $saleReturnRefund, 2);

            $marginPercent = '0.00';
            if (bccomp($netSaleAmount, '0.00', 2) > 0) {
                $marginPercent = bcdiv(bcmul($effectiveProfit, '100.00', 4), $netSaleAmount, 2);
            }

            $saleDate = $sale->sale_date ? (is_string($sale->sale_date) ? $sale->sale_date : $sale->sale_date->format('Y-m-d')) : '';
            $cashierName = $sale->creator?->name ?? 'System';
            $customerName = $sale->customer?->name ?? 'Walking Customer';
            $pmLabel = $sale->payment_method instanceof \BackedEnum ? $sale->payment_method->label() : ($sale->payment_method ?? 'Cash');
            $pmValue = $sale->payment_method instanceof \BackedEnum ? $sale->payment_method->value : ($sale->payment_method ?? 'cash');

            $row = [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'date' => $saleDate,
                'sale_date' => $saleDate,
                'cashier' => $cashierName,
                'cashier_name' => $cashierName,
                'customer' => $customerName,
                'customer_name' => $customerName,
                'payment_method' => $pmValue,
                'payment_method_label' => $pmLabel,
                'total' => (string) $sale->total,
                'paid_amount' => (string) $sale->paid_amount,
                'due_amount' => (string) $sale->outstanding_due,
            ];

            if (! $isManager) {
                $row['subtotal'] = (string) $sale->subtotal;
                $row['discount'] = (string) $sale->discount;
                $row['returns_refund'] = $saleReturnRefund;
                $row['net_sales'] = $netSaleAmount;
                $row['cogs'] = $effectiveCogs;
                $row['net_profit'] = $effectiveProfit;
                $row['margin_percent'] = $marginPercent.'%';
            }

            $rows->push($row);

            $totalSubtotal = bcadd($totalSubtotal, (string) $sale->subtotal, 2);
            $totalDiscount = bcadd($totalDiscount, (string) $sale->discount, 2);
            $totalSales = bcadd($totalSales, (string) $sale->total, 2);
            $totalPaid = bcadd($totalPaid, (string) $sale->paid_amount, 2);
            $totalDue = bcadd($totalDue, (string) $sale->outstanding_due, 2);
            $totalReturnsRefund = bcadd($totalReturnsRefund, $saleReturnRefund, 2);
            $totalCostRestored = bcadd($totalCostRestored, $saleCostRestored, 2);
            $totalProfitReversed = bcadd($totalProfitReversed, $saleProfitReversed, 2);
            $totalCogs = bcadd($totalCogs, $effectiveCogs, 2);
            $totalNetProfit = bcadd($totalNetProfit, $effectiveProfit, 2);
        }

        $overallNetSales = bcsub($totalSales, $totalReturnsRefund, 2);
        $overallMargin = '0.00';
        if (bccomp($overallNetSales, '0.00', 2) > 0) {
            $overallMargin = bcdiv(bcmul($totalNetProfit, '100.00', 4), $overallNetSales, 2);
        }

        $summary = [
            'sales_count' => $sales->count(),
            'total_sales' => $totalSales,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
        ];

        if (! $isManager) {
            $summary['gross_sales'] = $totalSubtotal;
            $summary['bill_discounts'] = $totalDiscount;
            $summary['returns_refund'] = $totalReturnsRefund;
            $summary['net_sales'] = $overallNetSales;
            $summary['cogs'] = $totalCogs;
            $summary['net_profit'] = $totalNetProfit;
            $summary['margin_percent'] = $overallMargin.'%';
        }

        return [
            'period' => [
                'from' => $period->fromDateString(),
                'to' => $period->toDateString(),
            ],
            'summary' => $summary,
            'rows' => $rows,
            'is_manager' => $isManager,
        ];
    }
}
