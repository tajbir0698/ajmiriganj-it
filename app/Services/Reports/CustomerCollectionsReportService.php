<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\CustomerPayment;
use Illuminate\Support\Collection;

class CustomerCollectionsReportService
{
    /**
     * @param  array{
     *     start_date?: ?string,
     *     end_date?: ?string,
     *     customer_id?: ?int,
     *     cashier_id?: ?int,
     *     payment_method?: ?string,
     *     account_id?: ?int,
     *     include_reversed?: bool
     * }  $filters
     * @return array{
     *     rows: Collection<int, array{
     *         id: int,
     *         payment_date: string,
     *         receipt_no: string,
     *         customer_id: int,
     *         customer_name: string,
     *         account_id: ?int,
     *         account_name: ?string,
     *         payment_method: string,
     *         payment_method_label: string,
     *         cashier_id: ?int,
     *         cashier_name: ?string,
     *         amount: string,
     *         is_reversed: bool,
     *         note: ?string
     *     }>,
     *     totals: array{
     *         total_amount: string,
     *         active_amount: string,
     *         reversed_amount: string,
     *         count: int
     *     }
     * }
     */
    public function generate(array $filters = []): array
    {
        $query = CustomerPayment::query()
            ->with(['customer', 'account', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['start_date'])) {
            $query->where('payment_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('payment_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['cashier_id'])) {
            $query->where('created_by', $filters['cashier_id']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        $includeReversed = (bool) ($filters['include_reversed'] ?? false);
        if (! $includeReversed) {
            $query->whereNull('reversed_at');
        }

        $payments = $query->get();

        $rows = collect();
        $totalAmount = '0.00';
        $activeAmount = '0.00';
        $reversedAmount = '0.00';

        foreach ($payments as $payment) {
            $amount = (string) $payment->amount;
            $isReversed = $payment->isReversed();

            if ($isReversed) {
                $reversedAmount = bcadd($reversedAmount, $amount, 2);
            } else {
                $activeAmount = bcadd($activeAmount, $amount, 2);
            }
            $totalAmount = bcadd($totalAmount, $amount, 2);

            $rows->push([
                'id' => $payment->id,
                'payment_date' => is_string($payment->payment_date) ? $payment->payment_date : $payment->payment_date->format('Y-m-d'),
                'receipt_no' => $payment->receipt_no ?: ('CPAY-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT)),
                'customer_id' => $payment->customer_id,
                'customer_name' => $payment->customer ? $payment->customer->name : 'N/A',
                'account_id' => $payment->account_id,
                'account_name' => $payment->account ? $payment->account->name : 'N/A',
                'payment_method' => $payment->payment_method->value,
                'payment_method_label' => $payment->payment_method->label(),
                'cashier_id' => $payment->created_by,
                'cashier_name' => $payment->creator ? $payment->creator->name : 'N/A',
                'amount' => $amount,
                'is_reversed' => $isReversed,
                'note' => $payment->note,
            ]);
        }

        return [
            'rows' => $rows,
            'totals' => [
                'total_amount' => $totalAmount,
                'active_amount' => $activeAmount,
                'reversed_amount' => $reversedAmount,
                'count' => $rows->count(),
            ],
        ];
    }
}
