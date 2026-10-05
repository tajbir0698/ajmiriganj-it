<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Account;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Collection;

class PaymentMethodReportService
{
    /**
     * Generate payment method report.
     *
     * @return array{
     *     period: array{from: ?string, to: ?string},
     *     by_method: Collection<string, mixed>,
     *     by_account: Collection<string, mixed>,
     *     total_net: string,
     * }
     */
    public function generate(ReportPeriod $period): array
    {
        // 1. Sales payments at POS
        $salesQuery = Sale::query()->where('status', SaleStatus::COMPLETED)->where('paid_amount', '>', 0);
        if ($period->from) {
            $salesQuery->whereDate('sale_date', '>=', $period->fromDateString());
        }
        if ($period->to) {
            $salesQuery->whereDate('sale_date', '<=', $period->toDateString());
        }
        $sales = $salesQuery->get(['payment_method', 'account_id', 'paid_amount']);

        // 2. Customer Payments (due collections)
        $cpayQuery = CustomerPayment::query()->whereNull('reversed_at');
        if ($period->from) {
            $cpayQuery->whereDate('payment_date', '>=', $period->fromDateString());
        }
        if ($period->to) {
            $cpayQuery->whereDate('payment_date', '<=', $period->toDateString());
        }
        $customerPayments = $cpayQuery->get(['payment_method', 'account_id', 'amount']);

        // 3. Sale Returns (cash refunds paid out)
        $returnsQuery = SaleReturn::query()->where('cash_refund', '>', 0);
        if ($period->from) {
            $returnsQuery->whereDate('return_date', '>=', $period->fromDateString());
        }
        if ($period->to) {
            $returnsQuery->whereDate('return_date', '<=', $period->toDateString());
        }
        $returns = $returnsQuery->get(['refund_payment_method', 'refund_account_id', 'cash_refund']);

        $accounts = Account::all()->keyBy('id');

        // Group by Method
        $totalInflowsAll = '0.00';
        $methods = collect(PaymentMethod::cases())->mapWithKeys(function ($m) use ($sales, $customerPayments, $returns, &$totalInflowsAll) {
            $key = $m->value;

            $methodSales = $sales->filter(fn ($s) => ($s->payment_method?->value ?? $s->payment_method) === $key);
            $methodPayments = $customerPayments->filter(fn ($p) => ($p->payment_method?->value ?? $p->payment_method) === $key);
            $methodReturns = $returns->filter(fn ($r) => ($r->refund_payment_method?->value ?? $r->refund_payment_method) === $key);

            $salesTotal = (string) $methodSales->sum('paid_amount');
            $collectionsTotal = (string) $methodPayments->sum('amount');
            $refundsTotal = (string) $methodReturns->sum('cash_refund');
            $txCount = $methodSales->count() + $methodPayments->count();

            $inflows = bcadd($salesTotal, $collectionsTotal, 2);
            $net = bcsub($inflows, $refundsTotal, 2);
            $totalInflowsAll = bcadd($totalInflowsAll, $inflows, 2);

            return [$key => [
                'label' => $m->label(),
                'method' => $key,
                'tx_count' => $txCount,
                'pos_sales' => $salesTotal,
                'pos_sales_total' => $salesTotal,
                'collections' => $collectionsTotal,
                'collections_total' => $collectionsTotal,
                'total_in' => $inflows,
                'total_inflow' => $inflows,
                'refunds_out' => $refundsTotal,
                'net_amount' => $net,
            ]];
        });

        // Group by Account
        $accountsReport = $accounts->map(function ($acc) use ($sales, $customerPayments, $returns) {
            $salesTotal = (string) $sales->where('account_id', $acc->id)->sum('paid_amount');
            $collectionsTotal = (string) $customerPayments->where('account_id', $acc->id)->sum('amount');
            $refundsTotal = (string) $returns->where('refund_account_id', $acc->id)->sum('cash_refund');

            $inflows = bcadd($salesTotal, $collectionsTotal, 2);
            $net = bcsub($inflows, $refundsTotal, 2);

            return [
                'account_name' => $acc->name,
                'type' => $acc->type?->value ?? 'cash',
                'pos_sales' => $salesTotal,
                'collections' => $collectionsTotal,
                'total_in' => $inflows,
                'total_inflow' => $inflows,
                'refunds_out' => $refundsTotal,
                'net_amount' => $net,
            ];
        });

        $totalNet = '0.00';
        foreach ($methods as $m) {
            $totalNet = bcadd($totalNet, $m['net_amount'], 2);
        }

        return [
            'period' => [
                'from' => $period->fromDateString(),
                'to' => $period->toDateString(),
            ],
            'rows' => $methods->values(),
            'methods' => $methods->values(),
            'by_method' => $methods,
            'by_account' => $accountsReport,
            'totals' => [
                'total_in' => $totalInflowsAll,
                'total_out' => bcsub($totalInflowsAll, $totalNet, 2),
                'net_cash_flow' => $totalNet,
            ],
            'total_inflow' => $totalInflowsAll,
            'total_net' => $totalNet,
        ];
    }
}
