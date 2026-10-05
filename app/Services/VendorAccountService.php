<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use App\Support\Money;
use Illuminate\Support\Collection;
use LogicException;

class VendorAccountService
{
    /**
     * Calculate current due for a vendor:
     * vendor_due = opening + sum(active purchase totals) - sum(active vendor payments)
     *              - sum(purchase_returns.credit_amount) + sum(purchase_returns.refund_received_amount).
     */
    public function getCurrentDue(Vendor $vendor): string
    {
        $opening = (string) ($vendor->opening_balance ?? '0.00');

        $purchasesTotal = (string) Purchase::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', PurchaseStatus::ACTIVE->value)
            ->sum('total');

        $paymentsTotal = (string) VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->whereNull('reversed_at')
            ->sum('amount');

        $creditTotal = (string) PurchaseReturn::query()
            ->where('vendor_id', $vendor->id)
            ->sum('credit_amount');

        $refundReceivedTotal = (string) PurchaseReturn::query()
            ->where('vendor_id', $vendor->id)
            ->sum('refund_received_amount');

        $step1 = bcadd($opening, $purchasesTotal, 2);
        $step2 = bcsub($step1, $paymentsTotal, 2);
        $step3 = bcsub($step2, $creditTotal, 2);

        return bcadd($step3, $refundReceivedTotal, 2);
    }

    public function getTotalPurchased(Vendor $vendor): string
    {
        return (string) Purchase::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', PurchaseStatus::ACTIVE->value)
            ->sum('total');
    }

    public function getTotalPaid(Vendor $vendor): string
    {
        return (string) VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    public function getLastPurchaseDate(Vendor $vendor): ?string
    {
        $date = Purchase::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', PurchaseStatus::ACTIVE->value)
            ->latest('purchase_date')
            ->value('purchase_date');

        return $date ? (is_string($date) ? $date : $date->format('Y-m-d')) : null;
    }

    public function getLastPaymentDate(Vendor $vendor): ?string
    {
        $date = VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->whereNull('reversed_at')
            ->latest('payment_date')
            ->value('payment_date');

        return $date ? (is_string($date) ? $date : $date->format('Y-m-d')) : null;
    }

    /**
     * Get remaining unallocated opening balance for a vendor.
     */
    public function getOutstandingOpening(Vendor $vendor): string
    {
        $opening = (string) ($vendor->opening_balance ?? '0.00');

        $allocatedToOpening = (string) VendorPaymentAllocation::query()
            ->whereHas('vendorPayment', function ($q) use ($vendor): void {
                $q->where('vendor_id', $vendor->id)->whereNull('reversed_at');
            })
            ->whereNull('purchase_id')
            ->sum('amount');

        $rem = bcsub($opening, $allocatedToOpening, 2);

        return bccomp($rem, '0.00', 2) > 0 ? $rem : '0.00';
    }

    /**
     * Get sum of per-bill outstanding amounts across active purchases for a vendor.
     * Outstanding per bill = max(0, total - (credit_amount - refund_received_amount) - allocations).
     */
    public function getOutstandingPurchasesTotal(Vendor $vendor): string
    {
        $purchases = Purchase::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', PurchaseStatus::ACTIVE->value)
            ->with(['returns', 'paymentAllocations' => function ($q): void {
                $q->whereHas('vendorPayment', function ($vp): void {
                    $vp->whereNull('reversed_at');
                });
            }])
            ->get();

        $totalOutstanding = '0.00';

        foreach ($purchases as $p) {
            $billTotal = (string) $p->total;

            // Credit absorbed by this bill's due is (credit_amount - refund_received_amount)
            $absorbedCredit = '0.00';
            foreach ($p->returns as $ret) {
                $netCreditOnBill = bcsub((string) $ret->credit_amount, (string) $ret->refund_received_amount, 2);
                $absorbedCredit = bcadd($absorbedCredit, $netCreditOnBill, 2);
            }

            $allocated = '0.00';
            foreach ($p->paymentAllocations as $alloc) {
                $allocated = bcadd($allocated, (string) $alloc->amount, 2);
            }

            $netBill = bcsub($billTotal, $absorbedCredit, 2);
            $billDue = bcsub($netBill, $allocated, 2);
            $effectiveDue = bccomp($billDue, '0.00', 2) > 0 ? $billDue : '0.00';

            $totalOutstanding = bcadd($totalOutstanding, $effectiveDue, 2);
        }

        return $totalOutstanding;
    }

    /**
     * Get total unallocated advance across active payments for a vendor.
     * Payment advance = max(0, payment.amount - sum(allocations)).
     */
    public function getTotalAdvance(Vendor $vendor): string
    {
        $payments = VendorPayment::query()
            ->where('vendor_id', $vendor->id)
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
    public function assertDueInvariant(Vendor $vendor): void
    {
        $globalDue = $this->getCurrentDue($vendor);
        $perBill = $this->getOutstandingPurchasesTotal($vendor);
        $opening = $this->getOutstandingOpening($vendor);
        $advance = $this->getTotalAdvance($vendor);

        $left = bcsub(bcadd($perBill, $opening, 2), $advance, 2);

        if (bccomp($left, $globalDue, 2) !== 0) {
            throw new LogicException(
                "Vendor due invariant failed for Vendor #{$vendor->id} ({$vendor->name}): ".
                "per_bill_outstanding ({$perBill}) + opening ({$opening}) - advance ({$advance}) = {$left}, ".
                "but global_due = {$globalDue}."
            );
        }
    }

    /**
     * Generate chronological vendor ledger with running balance.
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
    public function getLedger(Vendor $vendor): Collection
    {
        $entries = collect();

        // 1. Opening balance entry
        $balance = (string) ($vendor->opening_balance ?? '0.00');
        $entries->push([
            'date' => $vendor->created_at ? $vendor->created_at->format('Y-m-d') : date('Y-m-d'),
            'description' => 'Opening Balance',
            'reference' => 'OPENING',
            'bill_amount' => $balance,
            'paid_amount' => '0.00',
            'balance' => $balance,
            'sort_key' => ($vendor->created_at ? $vendor->created_at->timestamp : 0).'_0',
        ]);

        // 2. All purchases (including cancelled purchases and their reversals)
        $purchases = Purchase::query()
            ->where('vendor_id', $vendor->id)
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get();

        foreach ($purchases as $purchase) {
            $date = is_string($purchase->purchase_date)
                ? $purchase->purchase_date
                : $purchase->purchase_date->format('Y-m-d');

            $entries->push([
                'date' => $date,
                'description' => 'Purchase Bill '.$purchase->invoice_no.($purchase->vendor_invoice_no ? " (Vendor Ref: {$purchase->vendor_invoice_no})" : ''),
                'reference' => $purchase->invoice_no,
                'bill_amount' => (string) $purchase->total,
                'paid_amount' => '0.00',
                'sort_key' => strtotime($date).'_1_'.$purchase->id,
            ]);

            if ($purchase->isCancelled()) {
                $cancelDate = $purchase->updated_at->format('Y-m-d');
                $entries->push([
                    'date' => $cancelDate,
                    'description' => 'Purchase Cancelled Reversal '.$purchase->invoice_no,
                    'reference' => $purchase->invoice_no.'-CANCEL',
                    'bill_amount' => '-'.$purchase->total,
                    'paid_amount' => '0.00',
                    'sort_key' => $purchase->updated_at->timestamp.'_2_'.$purchase->id,
                ]);
            }
        }

        // 3. All vendor payments
        $payments = VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $date = is_string($payment->payment_date)
                ? $payment->payment_date
                : $payment->payment_date->format('Y-m-d');

            $ref = $payment->reference_no ?: ('PAY-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT));
            $purchaseRef = $payment->purchase ? " (for {$payment->purchase->invoice_no})" : '';

            if ($payment->isReversed()) {
                $entries->push([
                    'date' => $date,
                    'description' => "Payment via {$payment->payment_method->label()}{$purchaseRef} [REVERSED]",
                    'reference' => $ref,
                    'bill_amount' => '0.00',
                    'paid_amount' => '0.00',
                    'sort_key' => strtotime($date).'_3_'.$payment->id,
                ]);
            } else {
                $entries->push([
                    'date' => $date,
                    'description' => "Payment via {$payment->payment_method->label()}{$purchaseRef}",
                    'reference' => $ref,
                    'bill_amount' => '0.00',
                    'paid_amount' => (string) $payment->amount,
                    'sort_key' => strtotime($date).'_3_'.$payment->id,
                ]);
            }
        }

        // 4. All purchase returns
        $returns = PurchaseReturn::query()
            ->where('vendor_id', $vendor->id)
            ->orderBy('return_date')
            ->orderBy('id')
            ->get();

        foreach ($returns as $ret) {
            $date = is_string($ret->return_date)
                ? $ret->return_date
                : $ret->return_date->format('Y-m-d');

            // Credit amount reduces payable (paid_amount or negative bill_amount)
            $entries->push([
                'date' => $date,
                'description' => "Purchase Return {$ret->return_no} Credit",
                'reference' => $ret->return_no,
                'bill_amount' => '0.00',
                'paid_amount' => (string) $ret->credit_amount,
                'sort_key' => strtotime($date).'_4_'.$ret->id,
            ]);

            // If cash refund was received from vendor, it offsets payable reduction
            if (bccomp((string) $ret->refund_received_amount, '0.00', 2) > 0) {
                $entries->push([
                    'date' => $date,
                    'description' => "Purchase Return {$ret->return_no} Cash Refund Received",
                    'reference' => $ret->return_no.'-REFUND',
                    'bill_amount' => (string) $ret->refund_received_amount,
                    'paid_amount' => '0.00',
                    'sort_key' => strtotime($date).'_5_'.$ret->id,
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
