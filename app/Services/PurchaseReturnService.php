<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RecordTransactionData;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\ReturnSettlement;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Vendor;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseReturnService
{
    public function __construct(
        protected FifoStockService $fifoStockService,
        protected AccountService $accountService,
        protected VendorPaymentService $vendorPaymentService,
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Create a purchase return.
     *
     * @param  array<int, array{purchase_item_id: int, qty: string, unit_price?: string}>  $items
     * @param  array<int, mixed>  $attachments
     */
    public function createReturn(
        Purchase $purchase,
        array $items,
        ReturnSettlement $settlement,
        ?Account $refundAccount = null,
        ?PaymentMethod $refundMethod = null,
        ?string $reason = null,
        ?CarbonInterface $returnDate = null,
        array $attachments = [],
        ?int $userId = null
    ): PurchaseReturn {
        if ($purchase->status !== PurchaseStatus::ACTIVE) {
            throw new InvalidArgumentException("Cannot return items from purchase #{$purchase->invoice_no}: purchase is not active.");
        }

        if (empty($items)) {
            throw new InvalidArgumentException('Purchase return must contain at least one item.');
        }

        $date = $returnDate ?? now();
        $dateStr = $date->toDateString();
        $operatorId = $userId ?? auth()->id();

        return DB::transaction(function () use (
            $purchase,
            $items,
            $settlement,
            $refundAccount,
            $refundMethod,
            $reason,
            $dateStr,
            $attachments,
            $operatorId
        ): PurchaseReturn {
            /** @var Purchase $lockedPurchase */
            $lockedPurchase = Purchase::where('id', $purchase->id)->lockForUpdate()->firstOrFail();

            $returnNo = SequenceService::nextPurchaseReturnNo();
            $preparedLines = [];
            $totalCostRemoved = '0.00';
            $totalCreditAmount = '0.00';

            foreach ($items as $itemData) {
                $batchId = (int) $itemData['purchase_item_id'];
                $qty = bcadd((string) $itemData['qty'], '0.000', 3);

                if (bccomp($qty, '0.000', 3) <= 0) {
                    continue;
                }

                /** @var PurchaseItem|null $batch */
                $batch = PurchaseItem::where('id', $batchId)
                    ->where('purchase_id', $lockedPurchase->id)
                    ->lockForUpdate()
                    ->first();

                if (! $batch) {
                    throw new InvalidArgumentException("Batch #{$batchId} not found or does not belong to purchase #{$lockedPurchase->invoice_no}.");
                }

                // Deduct stock via FifoStockService (sole writer of remaining_qty and stock_qty)
                $this->fifoStockService->deductPurchaseReturnStock($batch, $qty);

                $unitCost = (string) $batch->unit_cost;
                $landedUnitCost = (string) $batch->landed_unit_cost;
                $lineCostRemoved = bcmul($qty, $landedUnitCost, 2);
                $totalCostRemoved = bcadd($totalCostRemoved, $lineCostRemoved, 2);

                // Agreed unit return price defaults to batch landed_unit_cost (or unit_cost)
                $unitCredit = isset($itemData['unit_price'])
                    ? bcadd((string) $itemData['unit_price'], '0.00', 2)
                    : bcadd($landedUnitCost, '0.00', 2);

                $lineCredit = bcmul($qty, $unitCredit, 2);
                $totalCreditAmount = bcadd($totalCreditAmount, $lineCredit, 2);

                $preparedLines[] = [
                    'purchase_item_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'landed_unit_cost' => $landedUnitCost,
                ];
            }

            if (empty($preparedLines)) {
                throw new InvalidArgumentException('Total return quantity must be greater than zero.');
            }

            $lossAmount = bcsub($totalCostRemoved, $totalCreditAmount, 2);

            // Settlement logic
            $refundReceivedAmount = '0.00';
            $creditAppliedToBill = '0.00';
            $currentBillDue = (string) $lockedPurchase->due_amount;

            if ($settlement === ReturnSettlement::REFUND_RECEIVED) {
                // The credit is applied to the returned bill's outstanding due first;
                // only the remainder is received as cash.

                if (bccomp($totalCreditAmount, $currentBillDue, 2) <= 0) {
                    // Entire credit is absorbed by bill due -> no cash refund
                    $creditAppliedToBill = $totalCreditAmount;
                    $refundReceivedAmount = '0.00';
                } else {
                    // Bill due is fully wiped out; remainder is cash refund received
                    $creditAppliedToBill = $currentBillDue;
                    $remainderCash = bcsub($totalCreditAmount, $currentBillDue, 2);
                    $refundReceivedAmount = $remainderCash;

                    if (! $refundAccount) {
                        throw new InvalidArgumentException('Refund account is required when cash refund is received.');
                    }

                    // Record IN transaction under Vendor Refund
                    $cat = AccountCategory::where('name', 'Vendor Refund')->first();
                    $categoryId = $cat?->id;

                    $trxData = new RecordTransactionData(
                        accountId: $refundAccount->id,
                        type: TransactionType::IN,
                        amount: $refundReceivedAmount,
                        categoryId: $categoryId,
                        date: $dateStr,
                        source: TransactionSource::SYSTEM,
                        description: "Cash refund for purchase return {$returnNo} (Purchase {$lockedPurchase->invoice_no})",
                        partyType: Vendor::class,
                        partyId: $lockedPurchase->vendor_id,
                        createdBy: $operatorId
                    );

                    $this->accountService->record($trxData);
                }
            } else {
                // reduce_due_credit: no cash refund; entire credit reduces bill due
                if (bccomp($totalCreditAmount, $currentBillDue, 2) > 0) {
                    throw new InvalidArgumentException(
                        "Cannot reduce due by ৳{$totalCreditAmount}: bill outstanding due is only ৳{$currentBillDue}. Use refund received settlement to receive cash for the excess."
                    );
                }
                $creditAppliedToBill = $totalCreditAmount;
                $refundReceivedAmount = '0.00';
            }

            // Update purchase returned_amount and recalculate due/status
            $lockedPurchase->returned_amount = bcadd((string) ($lockedPurchase->returned_amount ?? '0.00'), $creditAppliedToBill, 2);
            $this->vendorPaymentService->recalculatePurchaseDueAndStatus($lockedPurchase);

            // Create PurchaseReturn
            $purchaseReturn = PurchaseReturn::create([
                'return_no' => $returnNo,
                'purchase_id' => $lockedPurchase->id,
                'vendor_id' => $lockedPurchase->vendor_id,
                'return_date' => $dateStr,
                'total_cost_removed' => $totalCostRemoved,
                'credit_amount' => $totalCreditAmount,
                'refund_received_amount' => $refundReceivedAmount,
                'loss_amount' => $lossAmount,
                'settlement' => $settlement,
                'refund_account_id' => $refundAccount?->id,
                'refund_payment_method' => $refundMethod,
                'reason' => $reason ?? 'Purchase return',
                'created_by' => $operatorId,
            ]);

            foreach ($preparedLines as $line) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'purchase_item_id' => $line['purchase_item_id'],
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'landed_unit_cost' => $line['landed_unit_cost'],
                ]);
            }

            if (! empty($attachments)) {
                $this->attachmentService->sync($purchaseReturn, $attachments);
            }

            activity()
                ->performedOn($purchaseReturn)
                ->causedBy($operatorId)
                ->withProperties([
                    'return_no' => $returnNo,
                    'purchase_id' => $lockedPurchase->id,
                    'credit_amount' => $totalCreditAmount,
                    'refund_received_amount' => $refundReceivedAmount,
                ])
                ->log('purchase_return.created');

            return $purchaseReturn;
        });
    }
}
