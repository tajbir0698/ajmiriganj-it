<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use Illuminate\Support\Collection;

class PurchaseReportService
{
    /**
     * Generate purchase report.
     *
     * @param  array{vendor_id?: ?int, product_id?: ?int}  $filters
     * @return array{
     *     period: array{from: ?string, to: ?string},
     *     rows: Collection<int, array<string, mixed>>,
     *     summary: array<string, mixed>,
     * }
     */
    public function generate(ReportPeriod $period, array $filters = []): array
    {
        $query = Purchase::query()
            ->where('status', PurchaseStatus::ACTIVE)
            ->with(['vendor', 'creator', 'items.product', 'returns']);

        if ($period->from) {
            $query->whereDate('purchase_date', '>=', $period->fromDateString());
        }
        if ($period->to) {
            $query->whereDate('purchase_date', '<=', $period->toDateString());
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('items', function ($q) use ($filters): void {
                $q->where('product_id', $filters['product_id']);
            });
        }

        $purchases = $query->orderBy('purchase_date', 'desc')->orderBy('id', 'desc')->get();

        $rows = new Collection();
        $totalSubtotal = '0.00';
        $totalDiscount = '0.00';
        $totalShipping = '0.00';
        $totalPurchases = '0.00';
        $totalPaid = '0.00';
        $totalDue = '0.00';
        $totalReturned = '0.00';

        foreach ($purchases as $p) {
            $returnedCredit = (string) $p->returns->sum('credit_amount');
            $purchaseDate = $p->purchase_date ? (is_string($p->purchase_date) ? $p->purchase_date : $p->purchase_date->format('Y-m-d')) : '';
            $statusVal = $p->payment_status?->value ?? ($p->payment_status?->label() ?? 'due');

            $rows->push([
                'id' => $p->id,
                'invoice_no' => $p->invoice_no,
                'vendor_invoice_no' => $p->vendor_invoice_no ?? '',
                'date' => $purchaseDate,
                'purchase_date' => $purchaseDate,
                'vendor_name' => $p->vendor?->name ?? 'Unknown',
                'subtotal' => (string) $p->subtotal,
                'discount' => (string) $p->discount,
                'shipping_cost' => (string) $p->shipping_cost,
                'extra_cost' => (string) $p->shipping_cost,
                'total' => (string) $p->total,
                'paid_amount' => (string) $p->paid_amount,
                'due_amount' => (string) $p->due_amount,
                'returned_credit' => $returnedCredit,
                'credit_amount' => $returnedCredit,
                'payment_status' => $statusVal,
            ]);

            $totalSubtotal = bcadd($totalSubtotal, (string) $p->subtotal, 2);
            $totalDiscount = bcadd($totalDiscount, (string) $p->discount, 2);
            $totalShipping = bcadd($totalShipping, (string) $p->shipping_cost, 2);
            $totalPurchases = bcadd($totalPurchases, (string) $p->total, 2);
            $totalPaid = bcadd($totalPaid, (string) $p->paid_amount, 2);
            $totalDue = bcadd($totalDue, (string) $p->due_amount, 2);
            $totalReturned = bcadd($totalReturned, $returnedCredit, 2);
        }

        return [
            'period' => [
                'from' => $period->fromDateString(),
                'to' => $period->toDateString(),
            ],
            'rows' => $rows,
            'summary' => [
                'count' => $purchases->count(),
                'purchases_count' => $purchases->count(),
                'subtotal' => $totalSubtotal,
                'discount' => $totalDiscount,
                'shipping_cost' => $totalShipping,
                'extra_cost' => $totalShipping,
                'total' => $totalPurchases,
                'total_purchases' => $totalPurchases,
                'paid_amount' => $totalPaid,
                'total_paid' => $totalPaid,
                'due_amount' => $totalDue,
                'total_due' => $totalDue,
                'returned_credit' => $totalReturned,
                'credit_amount' => $totalReturned,
            ],
        ];
    }
}
