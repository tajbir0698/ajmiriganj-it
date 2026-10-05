<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RecordTransactionData;
use App\Enums\PaymentMethod;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleReturnItemBatch;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleReturnService
{
    public function __construct(
        protected FifoStockService $fifoStockService,
        protected AccountService $accountService,
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Create a partial or full sale return.
     *
     * @param  array<int, array{sale_item_id: int, qty: string}>  $items
     * @param  array<int, mixed>  $attachments
     */
    public function createReturn(
        Sale $sale,
        array $items,
        ?Account $refundAccount = null,
        ?PaymentMethod $refundMethod = null,
        ?string $reason = null,
        ?CarbonInterface $returnDate = null,
        array $attachments = [],
        ?int $userId = null
    ): SaleReturn {
        if ($sale->status !== SaleStatus::COMPLETED) {
            throw new InvalidArgumentException("Cannot return items from sale #{$sale->invoice_no}: sale is not completed.");
        }

        if (empty($items)) {
            throw new InvalidArgumentException('Sale return must contain at least one item.');
        }

        $date = $returnDate ?? now();
        $dateStr = $date->toDateString();
        $operatorId = $userId ?? auth()->id();

        return DB::transaction(function () use (
            $sale,
            $items,
            $refundAccount,
            $refundMethod,
            $reason,
            $dateStr,
            $attachments,
            $operatorId
        ): SaleReturn {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            $returnNo = SequenceService::nextSaleReturnNo();
            $preparedReturnItems = [];
            $totalRefundAmount = '0.00';
            $totalCostRestored = '0.00';

            // Pre-calculate line discount shares if sale had overall discount
            $saleSubtotal = (string) $lockedSale->subtotal;
            $saleDiscount = (string) ($lockedSale->discount ?? '0.00');

            foreach ($items as $itemData) {
                $saleItemId = (int) $itemData['sale_item_id'];
                $returnQty = bcadd((string) $itemData['qty'], '0.000', 3);

                if (bccomp($returnQty, '0.000', 3) <= 0) {
                    continue;
                }

                /** @var SaleItem|null $saleItem */
                $saleItem = SaleItem::where('id', $saleItemId)
                    ->where('sale_id', $lockedSale->id)
                    ->lockForUpdate()
                    ->first();

                if (! $saleItem) {
                    throw new InvalidArgumentException("Sale item #{$saleItemId} not found on sale #{$lockedSale->invoice_no}.");
                }

                $returnable = $saleItem->returnableQty();
                if (bccomp($returnQty, $returnable, 3) > 0) {
                    throw new InvalidArgumentException(
                        "Cannot return {$returnQty} units for item {$saleItem->product_name}: only {$returnable} units returnable."
                    );
                }

                // Calculate effective line price and overall-discount share
                $lineTotal = (string) $saleItem->line_total;
                $lineDiscountShare = '0.00';
                if (bccomp($saleDiscount, '0.00', 2) > 0 && bccomp($saleSubtotal, '0.00', 2) > 0) {
                    $ratio = bcdiv($lineTotal, $saleSubtotal, 8);
                    $lineDiscountShare = bcmul($saleDiscount, $ratio, 2);
                }

                $effectiveLineTotal = bcsub($lineTotal, $lineDiscountShare, 2);
                $unitRefund = bcdiv($effectiveLineTotal, (string) $saleItem->qty, 4);

                // Last-unit rounding exactness:
                // If this return completes the entire remaining quantity of this item,
                // line_refund = effectiveLineTotal - alreadyRefundedForThisItem.
                $projectedReturnedQty = bcadd((string) $saleItem->returned_qty, $returnQty, 3);
                $isLastUnits = bccomp($projectedReturnedQty, (string) $saleItem->qty, 3) === 0;

                if ($isLastUnits) {
                    $alreadyRefunded = bcmul((string) $saleItem->returned_qty, $unitRefund, 2);
                    $lineRefund = bcsub($effectiveLineTotal, $alreadyRefunded, 2);
                } else {
                    $lineRefund = bcmul($returnQty, $unitRefund, 2);
                }

                // Restore stock via FifoStockService (most recently consumed batch first)
                $batchAllocations = $this->fifoStockService->restore(
                    allocationsOrSaleItem: $saleItem,
                    type: StockMovementType::SALE_RETURN,
                    user: auth()->user(),
                    returnQty: $returnQty
                );

                $lineCostRestored = '0.00';
                foreach ($batchAllocations as $ba) {
                    $sliceCost = bcmul((string) $ba['qty'], (string) $ba['unit_cost'], 4);
                    $lineCostRestored = bcadd($lineCostRestored, $sliceCost, 4);
                }
                $roundedLineCostRestored = bcadd($lineCostRestored, '0.00', 2);

                $totalRefundAmount = bcadd($totalRefundAmount, $lineRefund, 2);
                $totalCostRestored = bcadd($totalCostRestored, $roundedLineCostRestored, 2);

                $preparedReturnItems[] = [
                    'sale_item' => $saleItem,
                    'qty' => $returnQty,
                    'unit_refund' => $unitRefund,
                    'line_refund' => $lineRefund,
                    'line_cost_restored' => $roundedLineCostRestored,
                    'batches' => $batchAllocations,
                ];
            }

            if (empty($preparedReturnItems)) {
                throw new InvalidArgumentException('Total return quantity must be greater than zero.');
            }

            $profitReversed = bcsub($totalRefundAmount, $totalCostRestored, 2);

            // Settle refund: absorb due first, remainder in cash
            $currentOutstandingDue = (string) $lockedSale->outstanding_due;
            $dueReduction = bccomp($totalRefundAmount, $currentOutstandingDue, 2) <= 0
                ? $totalRefundAmount
                : $currentOutstandingDue;

            $cashRefund = bcsub($totalRefundAmount, $dueReduction, 2);

            if (bccomp($cashRefund, '0.00', 2) > 0) {
                if (! $refundAccount) {
                    throw new InvalidArgumentException('Refund account is required to payout cash refund of ৳'.$cashRefund.'.');
                }

                // Record OUT transaction under Sales Refund (affects_profit = false)
                $cat = AccountCategory::where('name', 'Sales Refund')->first();
                $categoryId = $cat?->id;

                $trxData = new RecordTransactionData(
                    accountId: $refundAccount->id,
                    type: TransactionType::OUT,
                    amount: $cashRefund,
                    categoryId: $categoryId,
                    date: $dateStr,
                    source: TransactionSource::SYSTEM,
                    description: "Cash refund for sale return {$returnNo} (Invoice {$lockedSale->invoice_no})",
                    partyType: $lockedSale->customer_id ? Customer::class : null,
                    partyId: $lockedSale->customer_id,
                    createdBy: $operatorId
                );

                $this->accountService->record($trxData);
            }

            // Update sale outstanding due, paid amount, and returned amount
            $lockedSale->outstanding_due = bcsub($currentOutstandingDue, $dueReduction, 2);
            if (bccomp($cashRefund, '0.00', 2) > 0) {
                $lockedSale->paid_amount = bcsub((string) $lockedSale->paid_amount, $cashRefund, 2);
            }
            $lockedSale->returned_amount = bcadd((string) ($lockedSale->returned_amount ?? '0.00'), $totalRefundAmount, 2);

            // Check if all items in the sale are now fully returned
            $hasUnreturnedItems = SaleItem::where('sale_id', $lockedSale->id)
                ->whereRaw('returned_qty < qty')
                ->exists();

            $lockedSale->return_status = $hasUnreturnedItems ? ReturnStatus::PARTIAL : ReturnStatus::FULL;
            $lockedSale->save();

            // Create SaleReturn record
            $saleReturn = SaleReturn::create([
                'return_no' => $returnNo,
                'sale_id' => $lockedSale->id,
                'customer_id' => $lockedSale->customer_id,
                'return_date' => $dateStr,
                'refund_amount' => $totalRefundAmount,
                'due_reduction' => $dueReduction,
                'cash_refund' => $cashRefund,
                'cost_restored' => $totalCostRestored,
                'profit_reversed' => $profitReversed,
                'refund_account_id' => $refundAccount?->id,
                'refund_payment_method' => $refundMethod,
                'reason' => $reason ?? 'Sale return',
                'created_by' => $operatorId,
            ]);

            foreach ($preparedReturnItems as $pri) {
                /** @var SaleItem $item */
                $item = $pri['sale_item'];
                $sri = SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'qty' => $pri['qty'],
                    'unit_refund' => $pri['unit_refund'],
                    'line_refund' => $pri['line_refund'],
                    'line_cost_restored' => $pri['line_cost_restored'],
                ]);

                foreach ($pri['batches'] as $ba) {
                    SaleReturnItemBatch::create([
                        'sale_return_item_id' => $sri->id,
                        'sale_item_batch_id' => $ba['sale_item_batch_id'],
                        'purchase_item_id' => $ba['purchase_item_id'],
                        'qty' => $ba['qty'],
                        'unit_cost' => $ba['unit_cost'],
                    ]);
                }
            }

            if (! empty($attachments)) {
                $this->attachmentService->sync($saleReturn, $attachments);
            }

            activity()
                ->performedOn($saleReturn)
                ->causedBy($operatorId)
                ->withProperties([
                    'return_no' => $returnNo,
                    'sale_id' => $lockedSale->id,
                    'refund_amount' => $totalRefundAmount,
                    'due_reduction' => $dueReduction,
                    'cash_refund' => $cashRefund,
                ])
                ->log('sale_return.created');

            return $saleReturn;
        });
    }

    /**
     * Return an entire sale with 1-click convenience.
     *
     * @param  array<int, mixed>  $attachments
     */
    public function returnWholeSale(
        Sale $sale,
        ?Account $refundAccount = null,
        ?PaymentMethod $refundMethod = null,
        ?string $reason = 'Return whole sale',
        ?CarbonInterface $returnDate = null,
        array $attachments = [],
        ?int $userId = null
    ): SaleReturn {
        $items = [];
        $saleItems = SaleItem::where('sale_id', $sale->id)->get();

        foreach ($saleItems as $item) {
            $returnable = $item->returnableQty();
            if (bccomp($returnable, '0.000', 3) > 0) {
                $items[] = [
                    'sale_item_id' => $item->id,
                    'qty' => $returnable,
                ];
            }
        }

        if (empty($items)) {
            throw new InvalidArgumentException("Sale #{$sale->invoice_no} has no returnable items.");
        }

        return $this->createReturn(
            sale: $sale,
            items: $items,
            refundAccount: $refundAccount,
            refundMethod: $refundMethod,
            reason: $reason,
            returnDate: $returnDate,
            attachments: $attachments,
            userId: $userId
        );
    }
}
