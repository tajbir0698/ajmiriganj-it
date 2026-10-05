<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\VendorPayment;
use Illuminate\Support\Collection;

class VendorPaymentReportService
{
    /**
     * @param  array{
     *     start_date?: ?string,
     *     end_date?: ?string,
     *     vendor_id?: ?int,
     *     payment_method?: ?string,
     *     account_id?: ?int,
     *     include_reversed?: bool
     * }  $filters
     * @return array{
     *     rows: Collection<int, array{
     *         id: int,
     *         payment_date: string,
     *         reference_no: string,
     *         vendor_id: int,
     *         vendor_name: string,
     *         account_id: ?int,
     *         account_name: ?string,
     *         payment_method: string,
     *         payment_method_label: string,
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
        $query = VendorPayment::query()
            ->with(['vendor', 'account'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['start_date'])) {
            $query->where('payment_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('payment_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
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
                'reference_no' => $payment->reference_no ?: ('PAY-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT)),
                'vendor_id' => $payment->vendor_id,
                'vendor_name' => $payment->vendor ? $payment->vendor->name : 'N/A',
                'account_id' => $payment->account_id,
                'account_name' => $payment->account ? $payment->account->name : 'N/A',
                'payment_method' => $payment->payment_method->value,
                'payment_method_label' => $payment->payment_method->label(),
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
