<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RecordTransactionData;
use App\Enums\BatchSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    public function __construct(
        protected AttachmentService $attachmentService,
        protected FifoStockService $fifoStockService,
        protected AccountService $accountService
    ) {}

    /**
     * Create a new purchase with FIFO batches, landed cost, price history, and accounting entries.
     *
     * @param  array{
     *     vendor_id: int|string,
     *     purchase_date: string,
     *     vendor_invoice_no?: ?string,
     *     shipping_cost?: float|int|string,
     *     discount?: float|int|string,
     *     paid_amount?: float|int|string,
     *     payment_method?: string|PaymentMethod,
     *     account_id?: int|string|null,
     *     reference_no?: ?string,
     *     note?: ?string,
     *     items: array<array{
     *         product_id: int|string,
     *         qty: float|int|string,
     *         unit_cost: float|int|string,
     *         new_sale_price?: float|int|string|null
     *     }>
     * }  $data
     * @param  array  $files  Uploaded bill/receipt files
     */
    public function createPurchase(array $data, array $files = [], ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($data, $files, $user): Purchase {
            // 1. Validation
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new InvalidArgumentException('Purchase must contain at least one product row.');
            }

            $shippingCost = (string) ($data['shipping_cost'] ?? '0.00');
            $discount = (string) ($data['discount'] ?? '0.00');
            $paidAmount = (string) ($data['paid_amount'] ?? '0.00');

            if (bccomp($shippingCost, '0.00', 2) < 0 || bccomp($discount, '0.00', 2) < 0 || bccomp($paidAmount, '0.00', 2) < 0) {
                throw new InvalidArgumentException('Amounts cannot be negative.');
            }

            // Calculate subtotal
            $subtotal = '0.00';
            foreach ($items as $index => $item) {
                $qty = (string) ($item['qty'] ?? '0');
                $unitCost = (string) ($item['unit_cost'] ?? '0');

                if (bccomp($qty, '0.000', 3) <= 0) {
                    throw new InvalidArgumentException("Row {$index}: Quantity must be greater than zero.");
                }
                if (bccomp($unitCost, '0.0000', 4) < 0) {
                    throw new InvalidArgumentException("Row {$index}: Unit cost cannot be negative.");
                }

                $lineTotal = Money::mul($qty, $unitCost, 4);
                $subtotal = Money::add($subtotal, $lineTotal, 2);
            }

            // Calculate total = subtotal - discount + shipping_cost
            $totalAfterDiscount = Money::sub($subtotal, $discount, 2);
            $total = Money::add($totalAfterDiscount, $shippingCost, 2);

            if (bccomp($total, '0.00', 2) < 0) {
                throw new InvalidArgumentException('Total purchase amount cannot be negative.');
            }

            if (bccomp($paidAmount, $total, 2) > 0) {
                throw new InvalidArgumentException("Paid amount [{$paidAmount}] cannot exceed total purchase amount [{$total}].");
            }

            if (bccomp($paidAmount, '0.00', 2) > 0 && empty($data['account_id'])) {
                throw new InvalidArgumentException('Payment account is required when paid amount is greater than zero.');
            }

            // 2. Proportional Landed Cost Allocation
            $calculatedItems = $this->calculateLandedCosts($items, $shippingCost, $discount);

            // 3. Sequential invoice number
            $invoiceNo = SequenceService::nextPurchaseInvoice();

            // 4. Payment status & due
            $dueAmount = Money::sub($total, $paidAmount, 2);
            if (bccomp($paidAmount, $total, 2) === 0) {
                $paymentStatus = PaymentStatus::PAID;
            } elseif (bccomp($paidAmount, '0.00', 2) === 0) {
                $paymentStatus = PaymentStatus::DUE;
            } else {
                $paymentStatus = PaymentStatus::PARTIAL;
            }

            $vendor = Vendor::findOrFail($data['vendor_id']);
            $userId = $user?->id ?? auth()->id();

            // 5. Create Purchase
            $purchase = Purchase::create([
                'invoice_no' => $invoiceNo,
                'vendor_invoice_no' => $data['vendor_invoice_no'] ?? null,
                'vendor_id' => $vendor->id,
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $paymentStatus,
                'status' => PurchaseStatus::ACTIVE,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            // 6. Create Purchase Items (Batches), Stock Movements, and update Products
            foreach ($calculatedItems as $calc) {
                $product = Product::lockForUpdate()->findOrFail($calc['product_id']);
                $qty = (string) $calc['qty'];
                $unitCost = (string) $calc['unit_cost'];
                $landedUnitCost = (string) $calc['landed_unit_cost'];
                $newSalePrice = ! empty($calc['new_sale_price']) ? (string) $calc['new_sale_price'] : null;

                // Track old values for PriceHistory
                $oldCost = (string) $product->last_cost;
                $oldSalePrice = (string) $product->sale_price;

                // Create FIFO batch row & stock movement via FifoStockService
                $this->fifoStockService->addBatch(
                    product: $product,
                    qty: $qty,
                    unitCost: $unitCost,
                    source: BatchSource::PURCHASE,
                    batchDate: Carbon::parse($purchase->purchase_date),
                    reference: $purchase,
                    purchaseId: $purchase->id,
                    newSalePrice: $newSalePrice,
                    landedUnitCost: $landedUnitCost,
                    user: $user
                );

                // Update product last_cost to landed unit cost
                $product->last_cost = $landedUnitCost;

                // Update sale price if provided and changed
                $salePriceChanged = false;
                if ($newSalePrice !== null && bccomp($newSalePrice, $oldSalePrice, 2) !== 0) {
                    $product->sale_price = $newSalePrice;
                    $salePriceChanged = true;
                }

                $costChanged = bccomp($landedUnitCost, $oldCost, 4) !== 0;

                // Write price history only if cost OR sale price changed
                if ($costChanged || $salePriceChanged) {
                    PriceHistory::create([
                        'product_id' => $product->id,
                        'old_cost' => $oldCost,
                        'new_cost' => $landedUnitCost,
                        'old_sale_price' => $oldSalePrice,
                        'new_sale_price' => $product->sale_price,
                        'reason' => "Purchase {$invoiceNo}",
                        'changed_by' => $userId,
                        'changed_at' => now(),
                    ]);
                }

                $product->save();
            }

            // 7. Payment and Accounts transaction
            if (bccomp($paidAmount, '0.00', 2) > 0) {
                $account = Account::lockForUpdate()->findOrFail($data['account_id']);
                $paymentMethod = $data['payment_method'] instanceof PaymentMethod
                    ? $data['payment_method']
                    : PaymentMethod::from((string) $data['payment_method']);

                $vendorPayment = VendorPayment::create([
                    'vendor_id' => $vendor->id,
                    'purchase_id' => $purchase->id,
                    'payment_date' => $data['purchase_date'],
                    'amount' => $paidAmount,
                    'payment_method' => $paymentMethod,
                    'account_id' => $account->id,
                    'reference_no' => $data['reference_no'] ?? null,
                    'note' => $data['note'] ?? "Payment for purchase {$invoiceNo}",
                    'created_by' => $userId,
                ]);

                \App\Models\VendorPaymentAllocation::create([
                    'vendor_payment_id' => $vendorPayment->id,
                    'purchase_id' => $purchase->id,
                    'amount' => $paidAmount,
                    'is_advance_application' => false,
                ]);

                $vendorPaymentCategory = AccountCategory::firstOrCreate(
                    ['name' => 'Vendor Payment'],
                    ['type' => 'expense', 'is_system' => true, 'is_active' => true, 'affects_profit' => false]
                );

                $this->accountService->record(new RecordTransactionData(
                    accountId: $account->id,
                    type: TransactionType::OUT,
                    amount: $paidAmount,
                    categoryId: $vendorPaymentCategory->id,
                    date: $data['purchase_date'],
                    source: TransactionSource::SYSTEM,
                    description: "Purchase payment for {$invoiceNo}",
                    partyType: Vendor::class,
                    partyId: $vendor->id,
                    referenceType: VendorPayment::class,
                    referenceId: $vendorPayment->id,
                    createdBy: $userId
                ));
            }

            // 8. Handle file uploads if any
            if (! empty($files)) {
                $this->attachmentService->storeMany($files, $purchase, $user);
            }

            // 9. Activity Log
            activity('purchases')
                ->performedOn($purchase)
                ->causedBy($user ?? auth()->user())
                ->withProperties(['invoice_no' => $invoiceNo, 'total' => $total, 'paid' => $paidAmount])
                ->log("Created purchase {$invoiceNo}");

            return $purchase->fresh(['vendor', 'items.product', 'payments', 'attachments']);
        });
    }

    /**
     * Cancel an untouched purchase, reversing stock, payments, and accounting transactions.
     */
    public function cancelPurchase(Purchase $purchase, string $reason, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason, $user): Purchase {
            if ($purchase->isCancelled()) {
                throw new DomainException("Purchase {$purchase->invoice_no} is already cancelled.");
            }

            // Block cancellation if purchase returns exist
            if ($purchase->returns()->exists()) {
                throw new DomainException("Purchase {$purchase->invoice_no} cannot be cancelled because purchase returns exist against it.");
            }

            // Block cancellation if allocations from other vendor payments exist
            $hasExternalAllocations = $purchase->allocations()
                ->whereHas('vendorPayment', fn ($q) => $q->where('purchase_id', '!=', $purchase->id)->orWhereNull('purchase_id'))
                ->exists();
            if ($hasExternalAllocations) {
                throw new DomainException("Purchase {$purchase->invoice_no} cannot be cancelled because external vendor payments are allocated to it.");
            }

            // Ensure all batches are untouched
            foreach ($purchase->items as $item) {
                if (! $item->isUntouched()) {
                    throw new DomainException(
                        "Purchase {$purchase->invoice_no} cannot be cancelled because batches have already been consumed. Please process a purchase return instead."
                    );
                }
            }

            $userId = $user?->id ?? auth()->id();

            // 1. Reverse stock for each batch via FifoStockService
            foreach ($purchase->items as $item) {
                $this->fifoStockService->reverseBatch(
                    batch: $item,
                    type: StockMovementType::PURCHASE,
                    reference: $purchase,
                    user: $user
                );
            }

            // 2. Reverse payments
            foreach ($purchase->payments as $payment) {
                $vendorCategory = AccountCategory::firstOrCreate(
                    ['name' => 'Vendor Payment'],
                    ['type' => 'expense']
                );

                // Create a compensating IN transaction to restore account balance
                $this->accountService->record(new RecordTransactionData(
                    accountId: $payment->account_id,
                    categoryId: $vendorCategory->id,
                    type: TransactionType::IN,
                    amount: (string) $payment->amount,
                    date: date('Y-m-d'),
                    source: TransactionSource::SYSTEM,
                    referenceType: VendorPayment::class,
                    referenceId: $payment->id,
                    partyType: Vendor::class,
                    partyId: $purchase->vendor_id,
                    description: "Reversal of payment for cancelled purchase {$purchase->invoice_no}",
                    createdBy: $userId,
                ));

                // Soft-delete the payment so it no longer counts against vendor due
                $payment->delete();
            }

            // 3. Mark purchase as cancelled
            $purchase->status = PurchaseStatus::CANCELLED;
            $purchase->note = trim(($purchase->note ?? '')."\n[CANCELLED: {$reason}]");
            $purchase->save();

            activity('purchases')
                ->performedOn($purchase)
                ->causedBy($user ?? auth()->user())
                ->withProperties(['reason' => $reason])
                ->log("Cancelled purchase {$purchase->invoice_no}: {$reason}");

            return $purchase->fresh();
        });
    }

    /**
     * Spread (shipping_cost - discount) across rows in proportion to each row's line value (qty x unit_cost).
     * landed_unit_cost = unit_cost + (row share / qty).
     * Sum of row shares equals exactly (shipping_cost - discount), with rounding remainder on the last row.
     * If all line values are zero, spread by qty instead.
     *
     * @param  array<array{product_id: mixed, qty: mixed, unit_cost: mixed, new_sale_price?: mixed}>  $items
     * @return array<array{product_id: mixed, qty: string, unit_cost: string, landed_unit_cost: string, new_sale_price?: mixed}>
     */
    public function calculateLandedCosts(array $items, string $shippingCost = '0.00', string $discount = '0.00'): array
    {
        $netAdjustment = Money::sub($shippingCost, $discount, 4); // net amount to spread
        $itemCount = count($items);

        if ($itemCount === 0) {
            return [];
        }

        // Calculate total line value and total quantity
        $totalLineValue = '0.0000';
        $totalQty = '0.000';
        $rowLineValues = [];

        foreach ($items as $i => $item) {
            $qty = (string) $item['qty'];
            $unitCost = (string) $item['unit_cost'];
            $lineValue = Money::mul($qty, $unitCost, 4);

            $rowLineValues[$i] = $lineValue;
            $totalLineValue = Money::add($totalLineValue, $lineValue, 4);
            $totalQty = Money::add($totalQty, $qty, 3);
        }

        $allocatedSum = '0.0000';
        $result = [];

        $useQtyProportion = (bccomp($totalLineValue, '0.0000', 4) === 0);

        foreach ($items as $i => $item) {
            $qty = (string) $item['qty'];
            $unitCost = (string) $item['unit_cost'];
            $isLast = ($i === $itemCount - 1);

            if ($isLast) {
                // Remainder on the last row so sum of shares is exactly netAdjustment
                $rowShare = Money::sub($netAdjustment, $allocatedSum, 4);
            } else {
                if ($useQtyProportion) {
                    $rowShare = (bccomp($totalQty, '0.000', 3) > 0)
                        ? bcdiv(bcmul($netAdjustment, $qty, 8), $totalQty, 4)
                        : '0.0000';
                } else {
                    $rowShare = bcdiv(bcmul($netAdjustment, $rowLineValues[$i], 8), $totalLineValue, 4);
                }

                $allocatedSum = Money::add($allocatedSum, $rowShare, 4);
            }

            // landed_unit_cost = unit_cost + (row_share / qty)
            $perUnitShare = bcdiv($rowShare, $qty, 4);
            $landedUnitCost = Money::add($unitCost, $perUnitShare, 4);

            // Avoid negative landed cost if discount exceeded cost
            if (bccomp($landedUnitCost, '0.0000', 4) < 0) {
                $landedUnitCost = '0.0000';
            }

            $result[] = [
                'product_id' => $item['product_id'],
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'landed_unit_cost' => $landedUnitCost,
                'new_sale_price' => $item['new_sale_price'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Calculate suggested sale price:
     * suggested_price = landed_unit_cost * (1 + margin / 100), rounded according to setting.
     */
    public function calculateSuggestedSalePrice(string $landedUnitCost, ?float $marginPercent = null): string
    {
        $margin = $marginPercent ?? (float) Setting::get('default_target_margin_percent', 25);
        $roundTo = (int) Setting::get('round_suggested_price_to', 1);

        $multiplier = bcadd('1', bcdiv((string) $margin, '100', 6), 6);
        $rawPrice = (float) bcmul($landedUnitCost, $multiplier, 4);

        if ($roundTo > 0) {
            $rounded = round($rawPrice / $roundTo) * $roundTo;
        } else {
            $rounded = round($rawPrice, 2);
        }

        return number_format($rounded, 2, '.', '');
    }

    /**
     * Calculate profit percentage:
     * (sale_price - landed_cost) / landed_cost * 100
     */
    public function calculateProfitMargin(string $salePrice, string $landedUnitCost): string
    {
        if (bccomp($landedUnitCost, '0.0000', 4) <= 0) {
            return '0.0';
        }

        $profit = bcsub($salePrice, $landedUnitCost, 4);
        $ratio = bcdiv($profit, $landedUnitCost, 6);
        $margin = bcmul($ratio, '100', 1);

        return $margin;
    }
}
