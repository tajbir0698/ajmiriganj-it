<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\Money;
use Illuminate\Support\Collection;
use LogicException;

class CustomerAccountService
{
    /**
     * Compute current due for a customer at scale 2:
     * customer_due = opening_balance + sum(sales.due_amount, completed sales)
     *                - sum(customer_payments.amount, excluding reversed) - sum(sale_returns.due_reduction).
     */
    public function getCurrentDue(Customer $customer): string
    {
        $opening = bcadd((string) ($customer->opening_balance ?? '0.00'), '0.00', 2);

        $salesDueRaw = (string) Sale::where('customer_id', $customer->id)
            ->where('status', SaleStatus::COMPLETED)
            ->sum('due_amount');

        $paymentsRaw = (string) CustomerPayment::where('customer_id', $customer->id)
            ->whereNull('reversed_at')
            ->sum('amount');

        $returnsDueReductionRaw = (string) SaleReturn::where('customer_id', $customer->id)
            ->sum('due_reduction');

        $totalSalesDue = bcadd($opening, $salesDueRaw, 2);
        $totalPaid = bcadd($paymentsRaw, $returnsDueReductionRaw, 2);

        return bcsub($totalSalesDue, $totalPaid, 2);
    }

    /**
     * Batch compute the current due for a collection/array of customer IDs to eliminate N+1 queries.
     *
     * @param  iterable<int>  $customerIds
     * @return array<int, string> [customerId => currentDue]
     */
    public function getDueForCustomers(iterable $customerIds): array
    {
        $ids = is_array($customerIds) ? $customerIds : (new Collection($customerIds))->all();

        if (empty($ids)) {
            return [];
        }

        $customers = Customer::whereIn('id', $ids)->get(['id', 'opening_balance'])->keyBy('id');

        $saleDues = Sale::whereIn('customer_id', $ids)
            ->where('status', SaleStatus::COMPLETED)
            ->selectRaw('customer_id, SUM(due_amount) as total_due')
            ->groupBy('customer_id')
            ->pluck('total_due', 'customer_id');

        $payments = CustomerPayment::whereIn('customer_id', $ids)
            ->whereNull('reversed_at')
            ->selectRaw('customer_id, SUM(amount) as total_payments')
            ->groupBy('customer_id')
            ->pluck('total_payments', 'customer_id');

        $returns = SaleReturn::whereIn('customer_id', $ids)
            ->selectRaw('customer_id, SUM(due_reduction) as total_reduction')
            ->groupBy('customer_id')
            ->pluck('total_reduction', 'customer_id');

        $dues = [];
        foreach ($ids as $id) {
            $customer = $customers->get($id);
            $opening = $customer ? bcadd((string) $customer->opening_balance, '0.00', 2) : '0.00';
            $saleDue = bcadd((string) ($saleDues->get($id) ?? '0.00'), '0.00', 2);
            $paid = bcadd((string) ($payments->get($id) ?? '0.00'), '0.00', 2);
            $reduction = bcadd((string) ($returns->get($id) ?? '0.00'), '0.00', 2);

            $totalDue = bcadd($opening, $saleDue, 2);
            $totalCredit = bcadd($paid, $reduction, 2);
            $dues[$id] = bcsub($totalDue, $totalCredit, 2);
        }

        return $dues;
    }

    /**
     * Get remaining unallocated opening balance for a customer.
     */
    public function getOutstandingOpening(Customer $customer): string
    {
        $opening = (string) ($customer->opening_balance ?? '0.00');

        $allocatedToOpening = (string) CustomerPaymentAllocation::query()
            ->whereHas('customerPayment', function ($q) use ($customer): void {
                $q->where('customer_id', $customer->id)->whereNull('reversed_at');
            })
            ->whereNull('sale_id')
            ->sum('amount');

        $rem = bcsub($opening, $allocatedToOpening, 2);

        return bccomp($rem, '0.00', 2) > 0 ? $rem : '0.00';
    }

    /**
     * Get sum of per-bill outstanding amounts across completed sales for a customer.
     */
    public function getOutstandingSalesTotal(Customer $customer): string
    {
        $sum = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::COMPLETED)
            ->sum('outstanding_due');

        return bcadd((string) $sum, '0.00', 2);
    }

    /**
     * Get total unallocated advance across active customer payments.
     */
    public function getTotalAdvance(Customer $customer): string
    {
        $payments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->whereNull('reversed_at')
            ->with('allocations')
            ->get();

        $totalAdvance = '0.00';

        foreach ($payments as $payment) {
            $paymentAmount = (string) $payment->amount;
            $allocated = '0.00';
            foreach ($payment->allocations as $alloc) {
                $allocated = bcadd($allocated, (string) $alloc->amount, 2);
            }
            $advance = bcsub($paymentAmount, $allocated, 2);
            if (bccomp($advance, '0.00', 2) > 0) {
                $totalAdvance = bcadd($totalAdvance, $advance, 2);
            }
        }

        return $totalAdvance;
    }

    /**
     * Assert the fundamental due invariant:
     * sum(per-bill outstanding) + outstanding opening balance - advance == global due.
     */
    public function assertDueInvariant(Customer $customer): void
    {
        $globalDue = $this->getCurrentDue($customer);
        $perBill = $this->getOutstandingSalesTotal($customer);
        $opening = $this->getOutstandingOpening($customer);
        $advance = $this->getTotalAdvance($customer);

        $left = bcsub(bcadd($perBill, $opening, 2), $advance, 2);

        if (bccomp($left, $globalDue, 2) !== 0) {
            throw new LogicException(
                "Customer due invariant failed for Customer #{$customer->id} ({$customer->name}): ".
                "per_bill_outstanding ({$perBill}) + opening ({$opening}) - advance ({$advance}) = {$left}, ".
                "but global_due = {$globalDue}."
            );
        }
    }

    /**
     * Generate chronological customer ledger with running balance.
     *
     * @return Collection<int, array{
     *     date: string,
     *     description: string,
     *     reference: string,
     *     bill_amount: string,
     *     paid_amount: string,
     *     balance: string
     * }>
     */
    public function getLedger(Customer $customer): Collection
    {
        $entries = collect();

        // 1. Opening balance entry
        $balance = (string) ($customer->opening_balance ?? '0.00');
        $entries->push([
            'date' => $customer->created_at ? $customer->created_at->format('Y-m-d') : date('Y-m-d'),
            'description' => 'Opening Balance',
            'reference' => 'OPENING',
            'bill_amount' => $balance,
            'paid_amount' => '0.00',
            'balance' => $balance,
            'sort_key' => ($customer->created_at ? $customer->created_at->timestamp : 0).'_0',
        ]);

        // 2. Completed sales (only due_amount adds to customer due ledger)
        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::COMPLETED)
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            $date = is_string($sale->sale_date)
                ? $sale->sale_date
                : $sale->sale_date->format('Y-m-d');

            $entries->push([
                'date' => $date,
                'description' => "Invoice {$sale->invoice_no} (Due: {$sale->due_amount})",
                'reference' => $sale->invoice_no,
                'bill_amount' => (string) $sale->due_amount,
                'paid_amount' => '0.00',
                'sort_key' => strtotime($date).'_1_'.$sale->id,
            ]);
        }

        // 3. Customer payments
        $payments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $date = is_string($payment->payment_date)
                ? $payment->payment_date
                : $payment->payment_date->format('Y-m-d');

            $ref = $payment->receipt_no ?: ('CPAY-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT));

            if ($payment->isReversed()) {
                $entries->push([
                    'date' => $date,
                    'description' => "Due Payment via {$payment->payment_method->label()} [REVERSED]",
                    'reference' => $ref,
                    'bill_amount' => '0.00',
                    'paid_amount' => '0.00',
                    'sort_key' => strtotime($date).'_2_'.$payment->id,
                ]);
            } else {
                $entries->push([
                    'date' => $date,
                    'description' => "Due Payment via {$payment->payment_method->label()}",
                    'reference' => $ref,
                    'bill_amount' => '0.00',
                    'paid_amount' => (string) $payment->amount,
                    'sort_key' => strtotime($date).'_2_'.$payment->id,
                ]);
            }
        }

        // 4. Sale returns (due reduction reduces customer balance)
        $returns = SaleReturn::query()
            ->where('customer_id', $customer->id)
            ->orderBy('return_date')
            ->orderBy('id')
            ->get();

        foreach ($returns as $ret) {
            $date = is_string($ret->return_date)
                ? $ret->return_date
                : $ret->return_date->format('Y-m-d');

            if (bccomp((string) $ret->due_reduction, '0.00', 2) > 0) {
                $entries->push([
                    'date' => $date,
                    'description' => "Sale Return {$ret->return_no} Due Reduction",
                    'reference' => $ret->return_no,
                    'bill_amount' => '0.00',
                    'paid_amount' => (string) $ret->due_reduction,
                    'sort_key' => strtotime($date).'_3_'.$ret->id,
                ]);
            }
        }

        // Sort chronologically and compute running balance
        $sorted = $entries->sortBy('sort_key')->values();

        $runningBalance = '0.00';
        $finalLedger = collect();

        foreach ($sorted as $index => $row) {
            if ($index === 0 && $row['reference'] === 'OPENING') {
                $runningBalance = $row['bill_amount'];
                $row['balance'] = $runningBalance;
            } else {
                $runningBalance = Money::add($runningBalance, $row['bill_amount']);
                $runningBalance = Money::sub($runningBalance, $row['paid_amount']);
                $row['balance'] = $runningBalance;
            }
            unset($row['sort_key']);
            $finalLedger->push($row);
        }

        return $finalLedger;
    }
}
