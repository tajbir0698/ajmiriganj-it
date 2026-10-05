<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\PurchaseReturn;
use App\Models\StockAdjustment;
use App\Services\BusinessFinanceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StockLossesReportService
{
    public function __construct(
        protected BusinessFinanceService $businessFinanceService
    ) {}

    /**
     * @param  array{
     *     start_date?: ?string,
     *     end_date?: ?string,
     *     product_id?: ?int
     * }  $filters
     * @return array{
     *     adjustments: Collection<int, array{
     *         id: int,
     *         date: string,
     *         type: string,
     *         product_name: string,
     *         qty: string,
     *         total_cost: string,
     *         reason: ?string,
     *         created_by: ?string
     *     }>,
     *     purchase_returns: Collection<int, array{
     *         id: int,
     *         date: string,
     *         return_no: string,
     *         vendor_name: string,
     *         total_cost_removed: string,
     *         credit_amount: string,
     *         loss_amount: string
     *     }>,
     *     totals: array{
     *         adjustment_losses: string,
     *         purchase_return_losses: string,
     *         total_stock_losses: string
     *     }
     * }
     */
    public function generate(array $filters = []): array
    {
        $startDate = ! empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = ! empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        $adjQuery = StockAdjustment::losses()->with(['product', 'creator'])->orderBy('adjusted_at', 'desc');
        if ($startDate) {
            $adjQuery->whereDate('adjusted_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $adjQuery->whereDate('adjusted_at', '<=', $endDate->toDateString());
        }
        if (! empty($filters['product_id'])) {
            $adjQuery->where('product_id', $filters['product_id']);
        }

        $adjustments = $adjQuery->get();

        $adjRows = collect();
        $totalAdjCost = '0.00';
        foreach ($adjustments as $adj) {
            $cost = bcadd((string) $adj->total_cost, '0.00', 2);
            $totalAdjCost = bcadd($totalAdjCost, $cost, 2);

            $adjRows->push([
                'id' => $adj->id,
                'date' => is_string($adj->adjusted_at) ? $adj->adjusted_at : $adj->adjusted_at->format('Y-m-d H:i'),
                'type' => is_object($adj->type) && enum_exists(get_class($adj->type)) ? $adj->type->value : (string) $adj->type,
                'product_name' => $adj->product ? $adj->product->name : 'N/A',
                'qty' => (string) $adj->qty,
                'total_cost' => $cost,
                'reason' => $adj->reason,
                'created_by' => $adj->creator ? $adj->creator->name : 'N/A',
            ]);
        }

        $prQuery = PurchaseReturn::query()->with('vendor')->orderBy('return_date', 'desc');
        if ($startDate) {
            $prQuery->whereDate('return_date', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $prQuery->whereDate('return_date', '<=', $endDate->toDateString());
        }

        $purchaseReturns = $prQuery->get();

        $prRows = collect();
        $totalPrLosses = '0.00';
        foreach ($purchaseReturns as $pr) {
            $costRemoved = bcadd((string) $pr->total_cost_removed, '0.00', 2);
            $credit = bcadd((string) $pr->credit_amount, '0.00', 2);
            $loss = bcadd((string) $pr->loss_amount, '0.00', 2);

            $totalPrLosses = bcadd($totalPrLosses, $loss, 2);

            $prRows->push([
                'id' => $pr->id,
                'date' => is_string($pr->return_date) ? $pr->return_date : $pr->return_date->format('Y-m-d'),
                'return_no' => $pr->return_no,
                'vendor_name' => $pr->vendor ? $pr->vendor->name : 'N/A',
                'total_cost_removed' => $costRemoved,
                'credit_amount' => $credit,
                'loss_amount' => $loss,
            ]);
        }

        $netLoss = bcadd($totalAdjCost, $totalPrLosses, 2);

        return [
            'adjustments' => $adjRows,
            'purchase_returns' => $prRows,
            'totals' => [
                'adjustment_losses' => $totalAdjCost,
                'purchase_return_losses' => $totalPrLosses,
                'total_stock_losses' => $netLoss,
            ],
        ];
    }
}
