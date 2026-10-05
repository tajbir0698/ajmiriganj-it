<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RecordTransactionData;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerPaymentService
{
    public function __construct(
        protected AccountService $accountService,
        protected CustomerAccountService $customerAccountService,
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Collect due payment from a customer.
     *
     * @param  array<int|string, string>|string  $allocation 'auto' or map: ['opening' => '100.00', sale_id => '200.00']
     * @param  array<int, mixed>  $attachments
     */
    public function collect(
        Customer $customer,
        string $amount,
        PaymentMethod $method,
        Account $account,
        CarbonInterface|string $date,
        ?string $reference = null,
        ?string $note = null,
        array $attachments = [],
        string|array $allocation = 'auto',
        ?int $userId = null
    ): CustomerPayment {
        $cleanAmount = bcadd($amount, '0.00', 2);
        if (bccomp($cleanAmount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $dateStr = is_string($date) ? $date : $date->toDateString();
        $operatorId = $userId ?? auth()->id();

        return DB::transaction(function () use (
            $customer,
            $cleanAmount,
            $method,
            $account,
            $dateStr,
            $reference,
            $note,
            $attachments,
            $allocation,
            $operatorId
        ): CustomerPayment {
            $paymentNo = 'CPAY-'.str_pad((string) ((CustomerPayment::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);

            // 1. Create CustomerPayment
            $payment = CustomerPayment::create([
                'payment_no' => $paymentNo,
                'customer_id' => $customer->id,
                'payment_date' => $dateStr,
                'amount' => $cleanAmount,
                'payment_method' => $method,
                'account_id' => $account->id,
                'reference_no' => $reference,
                'note' => $note,
                'created_by' => $operatorId,
            ]);

            // 2. Record ONE transaction through AccountService (category: Due Collection)
            $category = AccountCategory::where('name', 'Due Collection')->first();
            $categoryId = $category?->id;

            $trxData = new RecordTransactionData(
                accountId: $account->id,
                type: TransactionType::IN,
                amount: $cleanAmount,
                categoryId: $categoryId,
                date: $dateStr,
                source: TransactionSource::SYSTEM,
                description: "Due collection from customer {$customer->name}".($reference ? " (Ref: {$reference})" : ''),
                partyType: Customer::class,
                partyId: $customer->id,
                referenceType: CustomerPayment::class,
                referenceId: $payment->id,
                createdBy: $operatorId
            );

            $this->accountService->record($trxData);

            // 3. Allocations
            $this->executeAllocations($payment, $cleanAmount, $allocation);

            // 4. Attachments if any
            if (! empty($attachments)) {
                $this->attachmentService->sync($payment, $attachments);
            }

            activity()
                ->performedOn($payment)
                ->causedBy($operatorId)
                ->withProperties(['amount' => $cleanAmount, 'customer_id' => $customer->id])
                ->log('customer_payment.created');

            return $payment;
        });
    }

    /**
     * Apply customer advance balance to unpaid sales or opening balance.
     * Draws from oldest payments' advances first and creates allocations with is_advance_application = true.
     * No transaction is created.
     *
     * @param  array<int|string, string>  $targets map: ['opening' => '100.00', sale_id => '200.00']
     */
    public function applyAdvance(Customer $customer, array $targets, ?int $userId = null): array
    {
        $operatorId = $userId ?? auth()->id();

        return DB::transaction(function () use ($customer, $targets, $operatorId): array {
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

            $availableAdvance = $this->customerAccountService->getTotalAdvance($customer);
            if (bccomp($totalRequested, $availableAdvance, 2) > 0) {
                throw new InvalidArgumentException(
                    "Requested advance allocation of {$totalRequested} exceeds available advance of {$availableAdvance}."
                );
            }

            $payments = CustomerPayment::query()
                ->where('customer_id', $customer->id)
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

                $saleId = $targetKey === 'opening' || $targetKey === 0 || $targetKey === '0' ? null : (int) $targetKey;
                $sale = null;

                if ($saleId !== null) {
                    /** @var Sale $sale */
                    $sale = Sale::where('id', $saleId)->where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
                    if ($sale->status !== SaleStatus::COMPLETED) {
                        throw new InvalidArgumentException("Sale #{$saleId} is not completed.");
                    }
                    if (bccomp($needed, (string) $sale->outstanding_due, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Cannot allocate {$needed} to sale #{$sale->invoice_no}: maximum outstanding due is {$sale->outstanding_due}."
                        );
                    }
                } else {
                    $outstandingOpening = $this->customerAccountService->getOutstandingOpening($customer);
                    if (bccomp($needed, $outstandingOpening, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Cannot allocate {$needed} to opening balance: maximum outstanding is {$outstandingOpening}."
                        );
                    }
                }

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

                    $alloc = CustomerPaymentAllocation::create([
                        'customer_payment_id' => $payment->id,
                        'sale_id' => $saleId,
                        'amount' => $slice,
                        'is_advance_application' => true,
                    ]);

                    $payment->allocations->push($alloc);
                    $createdAllocations[] = $alloc;
                    $needed = bcsub($needed, $slice, 2);

                    if ($sale !== null) {
                        $sale->outstanding_due = bcsub((string) $sale->outstanding_due, $slice, 2);
                        $sale->paid_amount = bcadd((string) $sale->paid_amount, $slice, 2);
                        $sale->save();
                    }
                }
            }

            activity()
                ->performedOn($customer)
                ->causedBy($operatorId)
                ->withProperties(['amount' => $totalRequested, 'targets' => $targets])
                ->log('customer_advance.applied');

            return $createdAllocations;
        });
    }

    /**
     * Reverse a customer payment.
     */
    public function reversePayment(CustomerPayment $payment, string $reason, ?int $userId = null): void
    {
        $operatorId = $userId ?? auth()->id();

        DB::transaction(function () use ($payment, $reason, $operatorId): void {
            /** @var CustomerPayment $lockedPayment */
            $lockedPayment = CustomerPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->isReversed()) {
                throw new InvalidArgumentException('Payment is already reversed.');
            }

            $hasAdvanceApplications = $lockedPayment->allocations()
                ->where('is_advance_application', true)
                ->exists();

            if ($hasAdvanceApplications) {
                throw new InvalidArgumentException(
                    'Cannot reverse payment: advance from this payment has already been applied to other bills. Undo those applications first.'
                );
            }

            if ($lockedPayment->transaction) {
                $this->accountService->reverse($lockedPayment->transaction, $reason, $operatorId, allowSystem: true);
            }

            // Rollback affected sales
            $allocations = $lockedPayment->allocations()->whereNotNull('sale_id')->get();
            foreach ($allocations as $alloc) {
                /** @var Sale|null $sale */
                $sale = Sale::where('id', $alloc->sale_id)->lockForUpdate()->first();
                if ($sale) {
                    $sale->outstanding_due = bcadd((string) $sale->outstanding_due, (string) $alloc->amount, 2);
                    $sale->paid_amount = bcsub((string) $sale->paid_amount, (string) $alloc->amount, 2);
                    if (bccomp((string) $sale->paid_amount, '0.00', 2) < 0) {
                        $sale->paid_amount = '0.00';
                    }
                    $sale->save();
                }
            }

            $lockedPayment->reversed_at = now();
            $lockedPayment->reversal_reason = $reason;
            $lockedPayment->save();

            activity()
                ->performedOn($lockedPayment)
                ->causedBy($operatorId)
                ->withProperties(['reason' => $reason])
                ->log('customer_payment.reversed');
        });
    }

    /**
     * Execute allocation logic for a new customer payment.
     *
     * @param  array<int|string, string>|string  $allocation
     */
    protected function executeAllocations(CustomerPayment $payment, string $totalAmount, string|array $allocation): void
    {
        $remainingToAllocate = $totalAmount;
        $customer = $payment->customer;

        if ($allocation === 'auto') {
            // 1. Auto-allocate: opening balance first
            $outstandingOpening = $this->customerAccountService->getOutstandingOpening($customer);
            if (bccomp($outstandingOpening, '0.00', 2) > 0 && bccomp($remainingToAllocate, '0.00', 2) > 0) {
                $openingSlice = bccomp($remainingToAllocate, $outstandingOpening, 2) <= 0
                    ? $remainingToAllocate
                    : $outstandingOpening;

                CustomerPaymentAllocation::create([
                    'customer_payment_id' => $payment->id,
                    'sale_id' => null,
                    'amount' => $openingSlice,
                    'is_advance_application' => false,
                ]);

                $remainingToAllocate = bcsub($remainingToAllocate, $openingSlice, 2);
            }

            // 2. Unpaid completed sales ordered by sale_date ASC, id ASC
            if (bccomp($remainingToAllocate, '0.00', 2) > 0) {
                $sales = Sale::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', SaleStatus::COMPLETED)
                    ->where('outstanding_due', '>', 0)
                    ->orderBy('sale_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($sales as $sale) {
                    if (bccomp($remainingToAllocate, '0.00', 2) <= 0) {
                        break;
                    }

                    $due = (string) $sale->outstanding_due;
                    if (bccomp($due, '0.00', 2) <= 0) {
                        continue;
                    }

                    $slice = bccomp($remainingToAllocate, $due, 2) <= 0 ? $remainingToAllocate : $due;

                    CustomerPaymentAllocation::create([
                        'customer_payment_id' => $payment->id,
                        'sale_id' => $sale->id,
                        'amount' => $slice,
                        'is_advance_application' => false,
                    ]);

                    $sale->outstanding_due = bcsub((string) $sale->outstanding_due, $slice, 2);
                    $sale->paid_amount = bcadd((string) $sale->paid_amount, $slice, 2);
                    $sale->save();

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
                    $outstandingOpening = $this->customerAccountService->getOutstandingOpening($customer);
                    if (bccomp($clean, $outstandingOpening, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Allocation to opening balance ({$clean}) exceeds outstanding opening balance ({$outstandingOpening})."
                        );
                    }

                    CustomerPaymentAllocation::create([
                        'customer_payment_id' => $payment->id,
                        'sale_id' => null,
                        'amount' => $clean,
                        'is_advance_application' => false,
                    ]);
                } else {
                    $saleId = (int) $targetKey;
                    /** @var Sale $sale */
                    $sale = Sale::where('id', $saleId)->where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();

                    if (bccomp($clean, (string) $sale->outstanding_due, 2) > 0) {
                        throw new InvalidArgumentException(
                            "Allocation to sale #{$sale->invoice_no} ({$clean}) exceeds outstanding due ({$sale->outstanding_due})."
                        );
                    }

                    CustomerPaymentAllocation::create([
                        'customer_payment_id' => $payment->id,
                        'sale_id' => $sale->id,
                        'amount' => $clean,
                        'is_advance_application' => false,
                    ]);

                    $sale->outstanding_due = bcsub((string) $sale->outstanding_due, $clean, 2);
                    $sale->paid_amount = bcadd((string) $sale->paid_amount, $clean, 2);
                    $sale->save();
                }
            }
        }
    }
}
