<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RecordTransactionData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Purchase;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VendorPaymentService
{
    public function __construct(
        protected AccountService $accountService,
        protected VendorAccountService $vendorAccountService,
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Record a vendor payment and allocate to bills or opening balance.
     *
     * @param  array<int|string, string>|string  $allocation 'auto' or map: ['opening' => '100.00', purchase_id => '200.00']
     * @param  array<int, mixed>  $attachments
     *
     * @throws InsufficientFundsException
     * @throws InvalidArgumentException
     */
    public function pay(
        Vendor $vendor,
        string $amount,
        PaymentMethod $method,
        Account $account,
        CarbonInterface|string $date,
        ?string $reference = null,
        ?string $note = null,
        array $attachments = [],
        string|array $allocation = 'auto',
        ?int $userId = null
    ): VendorPayment {
        $cleanAmount = bcadd($amount, '0.00', 2);
        if (bccomp($cleanAmount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $dateStr = is_string($date) ? $date : $date->toDateString();
        $operatorId = $userId ?? auth()->id();

        return DB::transaction(function () use (
            $vendor,
            $cleanAmount,
            $method,
            $account,
            $dateStr,
            $reference,
            $note,
            $attachments,
            $allocation,
            $operatorId
        ): VendorPayment {
            // 1. Create Vendor Payment record
            $payment = VendorPayment::create([
                'vendor_id' => $vendor->id,
                'payment_date' => $dateStr,
                'amount' => $cleanAmount,
                'payment_method' => $method,
                'account_id' => $account->id,
                'reference_no' => $reference,
                'note' => $note,
                'created_by' => $operatorId,
            ]);

            // 2. Record ONE transaction through AccountService
            $category = AccountCategory::where('name', 'Vendor Payment')->first();
            $categoryId = $category?->id;

            $trxData = new RecordTransactionData(
                accountId: $account->id,
                type: TransactionType::OUT,
                amount: $cleanAmount,
                categoryId: $categoryId,
                date: $dateStr,
                source: TransactionSource::SYSTEM,
                description: "Payment to vendor {$vendor->name}".($reference ? " (Ref: {$reference})" : ''),
                partyType: Vendor::class,
                partyId: $vendor->id,
                referenceType: VendorPayment::class,
                referenceId: $payment->id,
                createdBy: $operatorId
            );

            $this->accountService->record($trxData);

            // 3. Perform allocations
            $this->executeAllocations($payment, $cleanAmount, $allocation);

            // 4. Attachments if any
            if (! empty($attachments)) {
                $this->attachmentService->sync($payment, $attachments);
            }

            activity()
                ->performedOn($payment)
                ->causedBy($operatorId)
                ->withProperties(['amount' => $cleanAmount, 'vendor_id' => $vendor->id])
                ->log('vendor_payment.created');

            return $payment;
        });
    }

    /**
     * Apply vendor advance balance to unpaid purchases or opening balance.
     * Draws from oldest payments' advances first and creates allocations with is_advance_application = true.
     * No transaction is created.
     *
     * @param  array<int|string, string>  $targets map: ['opening' => '100.00', purchase_id => '200.00']
     */
    public function applyAdvance(Vendor $vendor, array $targets, ?int $userId = null): array
    {
        $operatorId = $userId ?? auth()->id();

        return DB::transaction(function () use ($vendor, $targets, $operatorId): array {
            // 1. Calculate total requested allocation
            $totalRequested = '0.00';
            foreach ($targets as $amt) {
                $clean = bcadd((string) $amt, '0.00', 2);
                if (bccomp($clean, '0.00', 2) < 0) {
                    throw new InvalidArgumentException('Allocation amount cannot be negative.');
                }
                $totalRequested = bcadd($totalRequested, $clean, 2);
            }

            if (bccomp($totalRequested, '0.00', 2) <= 0) {
                return [];
            }

            // 2. Check total advance available
            $availableAdvance = $this->vendorAccountService->getTotalAdvance($vendor);
            if (bccomp($totalRequested, $availableAdvance, 2) > 0) {
                throw new InvalidArgumentException(
                    "Requested advance allocation of {$totalRequested} exceeds available advance of {$availableAdvance}."
                );
            }

            // 3. Fetch active payments with remaining advance, oldest first
            $payments = VendorPayment::query()
                ->where('vendor_id', $vendor->id)
                ->whereNull('reversed_at')
                ->orderBy('payment_date', 'asc')
                ->orderBy('id', 'asc')
                ->with('allocations')
                ->lockForUpdate()
                ->get();

            $createdAllocations = [];

            foreach ($targets as $targetKey => $targetAmount) {
                $needed = bcadd((string) $targetAmount, '0.00', 2);
                if (bccomp($needed, '0.00', 2) <= 0) {
                    continue;
                }

                $purchaseId = $targetKey === 'opening' || $targetKey === 0 || $targetKey === '0' ? null : (int) $targetKey;
                $purchase = null;

                if ($purchaseId !== null) {
                    /** @var Purchase $purchase */
                    $purchase = Purchase::where('id', $purchaseId)->where('vendor_id', $vendor->id)->lockForUpdate()->firstOrFail();
                    if ($purchase->status !== PurchaseStatus::ACTIVE) {
                        throw new InvalidArgumentException("Purchase #{$purchaseId} is not active.");
                    }
                    if (bccomp($needed, (string) $purchase->due_amount, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Cannot allocate {$needed} to purchase #{$purchase->invoice_no}: maximum due is {$purchase->due_amount}."
                        );
                    }
                } else {
                    $outstandingOpening = $this->vendorAccountService->getOutstandingOpening($vendor);
                    if (bccomp($needed, $outstandingOpening, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Cannot allocate {$needed} to opening balance: maximum outstanding is {$outstandingOpening}."
                        );
                    }
                }

                // Draw from payments with remaining advance
                foreach ($payments as $payment) {
                    if (bccomp($needed, '0.00', 2) <= 0) {
                        break;
                    }

                    $paymentTotal = (string) $payment->amount;
                    $paymentAllocated = '0.00';
                    foreach ($payment->allocations as $a) {
                        $paymentAllocated = bcadd($paymentAllocated, (string) $a->amount, 2);
                    }
                    $paymentRemainingAdvance = bcsub($paymentTotal, $paymentAllocated, 2);

                    if (bccomp($paymentRemainingAdvance, '0.00', 2) <= 0) {
                        continue;
                    }

                    $slice = bccomp($paymentRemainingAdvance, $needed, 2) <= 0 ? $paymentRemainingAdvance : $needed;

                    $alloc = VendorPaymentAllocation::create([
                        'vendor_payment_id' => $payment->id,
                        'purchase_id' => $purchaseId,
                        'amount' => $slice,
                        'is_advance_application' => true,
                    ]);

                    // Refresh in-memory payment allocations
                    $payment->allocations->push($alloc);
                    $createdAllocations[] = $alloc;
                    $needed = bcsub($needed, $slice, 2);

                    // Update purchase if applicable
                    if ($purchase !== null) {
                        $purchase->paid_amount = bcadd((string) $purchase->paid_amount, $slice, 2);
                        $this->recalculatePurchaseDueAndStatus($purchase);
                    }
                }
            }

            activity()
                ->performedOn($vendor)
                ->causedBy($operatorId)
                ->withProperties(['amount' => $totalRequested, 'targets' => $targets])
                ->log('vendor_advance.applied');

            return $createdAllocations;
        });
    }

    /**
     * Reverse a vendor payment.
     */
    public function reversePayment(VendorPayment $payment, string $reason, ?int $userId = null): void
    {
        $operatorId = $userId ?? auth()->id();

        DB::transaction(function () use ($payment, $reason, $operatorId): void {
            /** @var VendorPayment $lockedPayment */
            $lockedPayment = VendorPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->isReversed()) {
                throw new InvalidArgumentException('Payment is already reversed.');
            }

            // Check if this payment has subsequent advance applications on it
            $hasAdvanceApplications = $lockedPayment->allocations()
                ->where('is_advance_application', true)
                ->exists();

            if ($hasAdvanceApplications) {
                throw new InvalidArgumentException(
                    'Cannot reverse payment: advance from this payment has already been applied to other bills. Undo those applications first.'
                );
            }

            // Reverse linked transaction through AccountService with allowSystem = true
            if ($lockedPayment->transaction) {
                $this->accountService->reverse($lockedPayment->transaction, $reason, $operatorId, allowSystem: true);
            }

            // Rollback affected purchases
            $allocations = $lockedPayment->allocations()->whereNotNull('purchase_id')->get();
            foreach ($allocations as $alloc) {
                /** @var Purchase|null $purchase */
                $purchase = Purchase::where('id', $alloc->purchase_id)->lockForUpdate()->first();
                if ($purchase) {
                    $purchase->paid_amount = bcsub((string) $purchase->paid_amount, (string) $alloc->amount, 2);
                    if (bccomp((string) $purchase->paid_amount, '0.00', 2) < 0) {
                        $purchase->paid_amount = '0.00';
                    }
                    $this->recalculatePurchaseDueAndStatus($purchase);
                }
            }

            $lockedPayment->reversed_at = now();
            $lockedPayment->reversal_reason = $reason;
            $lockedPayment->save();

            activity()
                ->performedOn($lockedPayment)
                ->causedBy($operatorId)
                ->withProperties(['reason' => $reason])
                ->log('vendor_payment.reversed');
        });
    }

    /**
     * Execute allocation logic for a new payment.
     *
     * @param  array<int|string, string>|string  $allocation
     */
    protected function executeAllocations(VendorPayment $payment, string $totalAmount, string|array $allocation): void
    {
        $remainingToAllocate = $totalAmount;
        $vendor = $payment->vendor;

        if ($allocation === 'auto') {
            // 1. Auto-allocate: opening balance first
            $outstandingOpening = $this->vendorAccountService->getOutstandingOpening($vendor);
            if (bccomp($outstandingOpening, '0.00', 2) > 0 && bccomp($remainingToAllocate, '0.00', 2) > 0) {
                $openingSlice = bccomp($remainingToAllocate, $outstandingOpening, 2) <= 0
                    ? $remainingToAllocate
                    : $outstandingOpening;

                VendorPaymentAllocation::create([
                    'vendor_payment_id' => $payment->id,
                    'purchase_id' => null,
                    'amount' => $openingSlice,
                    'is_advance_application' => false,
                ]);

                $remainingToAllocate = bcsub($remainingToAllocate, $openingSlice, 2);
            }

            // 2. Unpaid active purchases ordered by purchase_date ASC, id ASC
            if (bccomp($remainingToAllocate, '0.00', 2) > 0) {
                $purchases = Purchase::query()
                    ->where('vendor_id', $vendor->id)
                    ->where('status', PurchaseStatus::ACTIVE->value)
                    ->where('due_amount', '>', 0)
                    ->orderBy('purchase_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($purchases as $purchase) {
                    if (bccomp($remainingToAllocate, '0.00', 2) <= 0) {
                        break;
                    }

                    $due = (string) $purchase->due_amount;
                    if (bccomp($due, '0.00', 2) <= 0) {
                        continue;
                    }

                    $slice = bccomp($remainingToAllocate, $due, 2) <= 0 ? $remainingToAllocate : $due;

                    VendorPaymentAllocation::create([
                        'vendor_payment_id' => $payment->id,
                        'purchase_id' => $purchase->id,
                        'amount' => $slice,
                        'is_advance_application' => false,
                    ]);

                    $purchase->paid_amount = bcadd((string) $purchase->paid_amount, $slice, 2);
                    $this->recalculatePurchaseDueAndStatus($purchase);

                    $remainingToAllocate = bcsub($remainingToAllocate, $slice, 2);
                }
            }
        } elseif (is_array($allocation)) {
            // Manual allocation
            $sumAllocated = '0.00';
            foreach ($allocation as $targetKey => $amount) {
                $clean = bcadd((string) $amount, '0.00', 2);
                if (bccomp($clean, '0.00', 2) <= 0) {
                    continue;
                }

                $sumAllocated = bcadd($sumAllocated, $clean, 2);
                if (bccomp($sumAllocated, $totalAmount, 2) > 0) {
                    throw new InvalidArgumentException(
                        "Total manual allocations ({$sumAllocated}) exceed payment amount ({$totalAmount})."
                    );
                }

                if ($targetKey === 'opening' || $targetKey === 0 || $targetKey === '0') {
                    $outstandingOpening = $this->vendorAccountService->getOutstandingOpening($vendor);
                    if (bccomp($clean, $outstandingOpening, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Allocation to opening balance ({$clean}) exceeds outstanding opening balance ({$outstandingOpening})."
                        );
                    }

                    VendorPaymentAllocation::create([
                        'vendor_payment_id' => $payment->id,
                        'purchase_id' => null,
                        'amount' => $clean,
                        'is_advance_application' => false,
                    ]);
                } else {
                    $purchaseId = (int) $targetKey;
                    /** @var Purchase $purchase */
                    $purchase = Purchase::where('id', $purchaseId)->where('vendor_id', $vendor->id)->lockForUpdate()->firstOrFail();

                    if (bccomp($clean, (string) $purchase->due_amount, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Allocation to purchase #{$purchase->invoice_no} ({$clean}) exceeds outstanding due ({$purchase->due_amount})."
                        );
                    }

                    VendorPaymentAllocation::create([
                        'vendor_payment_id' => $payment->id,
                        'purchase_id' => $purchase->id,
                        'amount' => $clean,
                        'is_advance_application' => false,
                    ]);

                    $purchase->paid_amount = bcadd((string) $purchase->paid_amount, $clean, 2);
                    $this->recalculatePurchaseDueAndStatus($purchase);
                }
            }
        }
    }

    /**
     * Recalculate purchase due_amount and payment_status.
     * due_amount = total - returned_amount - paid_amount (never below 0).
     */
    public function recalculatePurchaseDueAndStatus(Purchase $purchase): void
    {
        $total = (string) $purchase->total;
        $returned = (string) ($purchase->returned_amount ?? '0.00');
        $paid = (string) ($purchase->paid_amount ?? '0.00');

        $netTotal = bcsub($total, $returned, 2);
        if (bccomp($netTotal, '0.00', 2) < 0) {
            $netTotal = '0.00';
        }

        $due = bcsub($netTotal, $paid, 2);
        if (bccomp($due, '0.00', 2) < 0) {
            $due = '0.00';
        }

        $purchase->due_amount = $due;

        if (bccomp($due, '0.00', 2) === 0) {
            $purchase->payment_status = PaymentStatus::PAID;
        } elseif (bccomp($paid, '0.00', 2) > 0) {
            $purchase->payment_status = PaymentStatus::PARTIAL;
        } else {
            $purchase->payment_status = PaymentStatus::DUE;
        }

        $purchase->save();
    }
}
