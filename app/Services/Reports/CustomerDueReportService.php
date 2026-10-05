<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\CustomerAccountService;
use Illuminate\Support\Collection;

class CustomerDueReportService
{
    public function __construct(
        protected CustomerAccountService $customerAccountService
    ) {}

    /**
     * @param  array{
     *     customer_id?: ?int,
     *     only_due?: bool
     * }  $filters
     * @return array{
     *     rows: Collection<int, array{
     *         customer_id: int,
     *         customer_name: string,
     *         phone: ?string,
     *         opening_balance: string,
     *         total_sales_due: string,
     *         total_paid: string,
     *         total_returns_reduction: string,
     *         current_due: string,
     *         advance: string
     *     }>,
     *     totals: array{
     *         opening_balance: string,
     *         total_sales_due: string,
     *         total_paid: string,
     *         total_returns_reduction: string,
     *         current_due: string,
     *         advance: string
     *     }
     * }
     */
    public function generate(array $filters = []): array
    {
        $query = Customer::query()->orderBy('name');

        if (! empty($filters['customer_id'])) {
            $query->where('id', $filters['customer_id']);
        }

        $customers = $query->get();
        $customerIds = $customers->pluck('id')->all();

        $saleDues = Sale::whereIn('customer_id', $customerIds)
            ->where('status', SaleStatus::COMPLETED)
            ->selectRaw('customer_id, SUM(due_amount) as total_due')
            ->groupBy('customer_id')
            ->pluck('total_due', 'customer_id');

        $payments = CustomerPayment::whereIn('customer_id', $customerIds)
            ->whereNull('reversed_at')
            ->selectRaw('customer_id, SUM(amount) as total_payments')
            ->groupBy('customer_id')
            ->pluck('total_payments', 'customer_id');

        $returns = SaleReturn::whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, SUM(due_reduction) as total_reduction')
            ->groupBy('customer_id')
            ->pluck('total_reduction', 'customer_id');

        // Advances
        $paymentAllocations = CustomerPaymentAllocation::query()
            ->whereHas('customerPayment', function ($q) use ($customerIds): void {
                $q->whereIn('customer_id', $customerIds)->whereNull('reversed_at');
            })
            ->join('customer_payments', 'customer_payment_allocations.customer_payment_id', '=', 'customer_payments.id')
            ->selectRaw('customer_payments.customer_id, SUM(customer_payment_allocations.amount) as total_allocated')
            ->groupBy('customer_payments.customer_id')
            ->pluck('total_allocated', 'customer_id');

        $onlyDue = (bool) ($filters['only_due'] ?? false);

        $rows = collect();
        $totalOpening = '0.00';
        $totalSalesDue = '0.00';
        $totalPaid = '0.00';
        $totalReduction = '0.00';
        $totalCurrentDue = '0.00';
        $totalAdvance = '0.00';

        foreach ($customers as $c) {
            $opening = bcadd((string) $c->opening_balance, '0.00', 2);
            $sDue = bcadd((string) ($saleDues->get($c->id) ?? '0.00'), '0.00', 2);
            $pPaid = bcadd((string) ($payments->get($c->id) ?? '0.00'), '0.00', 2);
            $rReduction = bcadd((string) ($returns->get($c->id) ?? '0.00'), '0.00', 2);

            $due = bcsub(bcadd($opening, $sDue, 2), bcadd($pPaid, $rReduction, 2), 2);

            // Compute advance = max(0, total payments - total allocated)
            $allocated = bcadd((string) ($paymentAllocations->get($c->id) ?? '0.00'), '0.00', 2);
            $adv = bcsub($pPaid, $allocated, 2);
            $advance = bccomp($adv, '0.00', 2) > 0 ? $adv : '0.00';

            if ($onlyDue && bccomp($due, '0.00', 2) <= 0) {
                continue;
            }

            // If everything is 0, skip
            if (
                bccomp($opening, '0.00', 2) === 0 &&
                bccomp($sDue, '0.00', 2) === 0 &&
                bccomp($pPaid, '0.00', 2) === 0 &&
                bccomp($due, '0.00', 2) === 0
            ) {
                continue;
            }

            $rows->push([
                'customer_id' => $c->id,
                'customer_name' => $c->name,
                'phone' => $c->phone,
                'opening_balance' => $opening,
                'total_sales_due' => $sDue,
                'total_paid' => $pPaid,
                'total_returns_reduction' => $rReduction,
                'current_due' => $due,
                'advance' => $advance,
            ]);

            $totalOpening = bcadd($totalOpening, $opening, 2);
            $totalSalesDue = bcadd($totalSalesDue, $sDue, 2);
            $totalPaid = bcadd($totalPaid, $pPaid, 2);
            $totalReduction = bcadd($totalReduction, $rReduction, 2);
            $totalCurrentDue = bcadd($totalCurrentDue, $due, 2);
            $totalAdvance = bcadd($totalAdvance, $advance, 2);
        }

        return [
            'rows' => $rows,
            'totals' => [
                'opening_balance' => $totalOpening,
                'total_sales_due' => $totalSalesDue,
                'total_paid' => $totalPaid,
                'total_returns_reduction' => $totalReduction,
                'current_due' => $totalCurrentDue,
                'advance' => $totalAdvance,
            ],
        ];
    }
}
