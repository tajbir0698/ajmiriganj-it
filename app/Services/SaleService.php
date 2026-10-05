<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\RecordTransactionData;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(
        protected FifoStockService $fifoStockService,
        protected AccountService $accountService
    ) {}

    /**
     * Create a sale and deplete stock using FIFO.
     *
     * @param  array{
     *     customer_id?: int|null,
     *     sale_date?: string|null,
     *     items: array<int, array{product_id: int, qty: string|numeric, discount?: string|numeric}>,
     *     discount?: string|numeric|null,
     *     paid_amount?: string|numeric|null,
     *     received_amount?: string|numeric|null,
     *     payment_method?: string|PaymentMethod|null,
     *     account_id?: int|null,
     *     idempotency_key?: string|null,
     *     note?: string|null
     * }  $data
     */
    public function createSale(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user): Sale {
            // 1. Idempotency Check: if key provided and sale exists, return it
            $idempotencyKey = ! empty($data['idempotency_key']) ? (string) $data['idempotency_key'] : null;
            if ($idempotencyKey) {
                $existingSale = Sale::where('idempotency_key', $idempotencyKey)->with(['items.batches'])->first();
                if ($existingSale) {
                    return $existingSale;
                }
            }

            // 2. Validate Items Presence
            if (empty($data['items']) || ! is_array($data['items'])) {
                throw new InvalidArgumentException('A sale must have at least one line item.');
            }

            $creditEnabled = (bool) Setting::get('credit_sales_enabled', false);
            $saleDate = ! empty($data['sale_date']) ? Carbon::parse($data['sale_date'])->toDateString() : now()->toDateString();

            // 3. Load & Lock Products to enforce current sale_price (never trust client prices)
            $productIds = array_map(fn ($item) => (int) $item['product_id'], $data['items']);
            $products = Product::whereIn('id', array_unique($productIds))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $linesToConsume = [];
            $preparedItems = [];
            $computedSubtotal = '0.00';

            foreach ($data['items'] as $index => $itemData) {
                $pid = (int) $itemData['product_id'];
                /** @var Product|null $product */
                $product = $products->get($pid);

                if (! $product) {
                    throw new InvalidArgumentException("Product with ID {$pid} not found.");
                }

                $qty = bcadd((string) $itemData['qty'], '0.000', 3);
                if (bccomp($qty, '0.000', 3) <= 0) {
                    throw new InvalidArgumentException("Quantity for product {$product->name} must be greater than zero.");
                }

                // Check unit fractional support if applicable
                if ($product->unit && ! $product->unit->allow_fractional) {
                    if (bccomp($qty, bcadd($qty, '0', 0), 3) !== 0) {
                        throw new InvalidArgumentException("Product {$product->name} does not allow fractional quantities.");
                    }
                }

                $unitPrice = bcadd((string) $product->sale_price, '0.00', 2);
                $grossLine = bcmul($qty, $unitPrice, 2);

                $itemDiscount = isset($itemData['discount']) ? bcadd((string) $itemData['discount'], '0.00', 2) : '0.00';
                if (bccomp($itemDiscount, '0.00', 2) < 0 || bccomp($itemDiscount, $grossLine, 2) > 0) {
                    throw new InvalidArgumentException("Item discount for {$product->name} cannot be negative or exceed the line gross total of {$grossLine}.");
                }

                $lineTotal = bcsub($grossLine, $itemDiscount, 2);
                $computedSubtotal = bcadd($computedSubtotal, $lineTotal, 2);

                $preparedItems[$index] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'line_total' => $lineTotal,
                ];

                $linesToConsume[$index] = [
                    'product_id' => $pid,
                    'qty' => $qty,
                ];
            }

            // 4. Validate Overall Discount
            $overallDiscount = isset($data['discount']) ? bcadd((string) $data['discount'], '0.00', 2) : '0.00';
            if (bccomp($overallDiscount, '0.00', 2) < 0 || bccomp($overallDiscount, $computedSubtotal, 2) > 0) {
                throw new InvalidArgumentException("Overall discount cannot be negative or exceed the sale subtotal of {$computedSubtotal}.");
            }

            $computedTotal = bcsub($computedSubtotal, $overallDiscount, 2);

            // 5. Payment & Credit Validation
            $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;
            /** @var Customer|null $customer */
            $customer = $customerId ? Customer::find($customerId) : null;

            $paidAmount = isset($data['paid_amount']) ? bcadd((string) $data['paid_amount'], '0.00', 2) : $computedTotal;
            $receivedAmount = isset($data['received_amount']) ? bcadd((string) $data['received_amount'], '0.00', 2) : $paidAmount;

            if (bccomp($paidAmount, '0.00', 2) < 0) {
                throw new InvalidArgumentException('Paid amount cannot be negative.');
            }

            if (! $creditEnabled) {
                // When credit is disabled, sale must be fully paid
                if (bccomp($paidAmount, $computedTotal, 2) !== 0) {
                    throw new InvalidArgumentException("Credit sales are disabled. Full payment of {$computedTotal} is required.");
                }
                if (bccomp($receivedAmount, $computedTotal, 2) < 0) {
                    throw new InvalidArgumentException("Received amount ({$receivedAmount}) cannot be less than total payable ({$computedTotal}).");
                }
                $dueAmount = '0.00';
                $changeAmount = bcsub($receivedAmount, $computedTotal, 2);
            } else {
                // When credit is enabled
                if (bccomp($paidAmount, $computedTotal, 2) < 0) {
                    if (! $customer) {
                        throw new InvalidArgumentException('A registered customer is required for credit / partial payment sales.');
                    }
                    $dueAmount = bcsub($computedTotal, $paidAmount, 2);
                } else {
                    $dueAmount = '0.00';
                    $paidAmount = $computedTotal; // Cap paid_amount to total; any excess is change
                }

                if (bccomp($receivedAmount, $paidAmount, 2) < 0) {
                    throw new InvalidArgumentException("Received amount ({$receivedAmount}) cannot be less than paid amount ({$paidAmount}).");
                }

                $changeAmount = bccomp($dueAmount, '0.00', 2) === 0
                    ? bcsub($receivedAmount, $computedTotal, 2)
                    : bcsub($receivedAmount, $paidAmount, 2);
            }

            // Payment method validation
            $paymentMethod = null;
            $accountId = null;

            if (bccomp($paidAmount, '0.00', 2) > 0) {
                if (empty($data['payment_method'])) {
                    throw new InvalidArgumentException('Payment method is required when paid amount is greater than zero.');
                }

                $paymentMethod = $data['payment_method'] instanceof PaymentMethod
                    ? $data['payment_method']
                    : PaymentMethod::from((string) $data['payment_method']);

                // Resolve account
                if (! empty($data['account_id'])) {
                    $account = Account::find($data['account_id']);
                } else {
                    $mappedAccountId = Setting::get('payment_account_'.$paymentMethod->value);
                    $account = $mappedAccountId ? Account::find($mappedAccountId) : null;
                    if (! $account) {
                        $account = Account::where('name', $paymentMethod->label())->first() ?: Account::first();
                    }
                }

                if (! $account) {
                    throw new InvalidArgumentException('No financial account resolved for this payment method.');
                }
                $accountId = $account->id;
            }

            // 6. Generate Invoice Number atomically
            $invoiceNo = SequenceService::nextInvoiceNo();

            // 7. Create Sale record
            /** @var Sale $sale */
            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'idempotency_key' => $idempotencyKey,
                'customer_id' => $customer?->id,
                'sale_date' => $saleDate,
                'subtotal' => $computedSubtotal,
                'discount' => $overallDiscount,
                'total' => $computedTotal,
                'paid_amount' => $paidAmount,
                'returned_amount' => '0.00',
                'due_amount' => $dueAmount,
                'outstanding_due' => $dueAmount,
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod?->value,
                'account_id' => $accountId,
                'status' => SaleStatus::COMPLETED,
                'return_status' => \App\Enums\ReturnStatus::NONE->value,
                'gross_profit' => '0.00',
                'net_profit' => '0.00',
                'note' => $data['note'] ?? null,
                'printed_count' => 0,
                'created_by' => $user->id,
            ]);

            // 8. Consume FIFO Stock via FifoStockService::consumeMany()
            // This will pre-flight all stock under locks and throw InsufficientStockException on shortage
            $consumptionResults = $this->fifoStockService->consumeMany(
                lines: $linesToConsume,
                type: StockMovementType::SALE,
                reference: $sale,
                user: $user
            );

            // 9. Create Sale Items, Allocations, and compute Profit
            $totalGrossProfit = '0.00';
            $hasNegativeProfitLine = false;

            foreach ($preparedItems as $index => $item) {
                $consumption = $consumptionResults[$index];

                $lineCost = $consumption->totalCost;
                $averageUnitCost = $consumption->averageUnitCost;
                $lineProfit = bcsub($item['line_total'], $lineCost, 2);
                $totalGrossProfit = bcadd($totalGrossProfit, $lineProfit, 2);

                if (bccomp($lineProfit, '0.00', 2) < 0) {
                    $hasNegativeProfitLine = true;
                }

                /** @var SaleItem $saleItem */
                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'sku' => $item['product']->sku,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'line_total' => $item['line_total'],
                    'unit_cost' => $averageUnitCost,
                    'line_cost' => $lineCost,
                    'line_profit' => $lineProfit,
                ]);

                // Store batch allocations
                foreach ($consumption->allocations as $alloc) {
                    SaleItemBatch::create([
                        'sale_item_id' => $saleItem->id,
                        'purchase_item_id' => $alloc['purchase_item_id'],
                        'qty' => $alloc['qty'],
                        'unit_cost' => $alloc['unit_cost'],
                    ]);
                }
            }

            // 10. Update Sales gross and net profit
            $netProfit = bcsub($totalGrossProfit, $overallDiscount, 2);
            $sale->gross_profit = $totalGrossProfit;
            $sale->net_profit = $netProfit;
            $sale->save();

            // 11. Record In Transaction in Account if paid_amount > 0
            if (bccomp($paidAmount, '0.00', 2) > 0 && $accountId) {
                $salesIncomeCategory = AccountCategory::firstOrCreate(
                    ['name' => 'Sales Income'],
                    ['type' => 'income', 'is_system' => true, 'is_active' => true, 'affects_profit' => false]
                );

                $this->accountService->record(new RecordTransactionData(
                    accountId: $accountId,
                    type: TransactionType::IN,
                    amount: $paidAmount,
                    categoryId: $salesIncomeCategory->id,
                    date: $saleDate,
                    source: TransactionSource::SYSTEM,
                    description: "Sales income for invoice {$sale->invoice_no}",
                    partyType: $customer ? Customer::class : null,
                    partyId: $customer?->id,
                    referenceType: Sale::class,
                    referenceId: $sale->id,
                    createdBy: $user->id
                ));
            }

            // 12. Log activity flag if items sold below cost
            if ($hasNegativeProfitLine) {
                activity('sale')
                    ->performedOn($sale)
                    ->causedBy($user)
                    ->withProperties(['flag' => 'negative_profit_detected'])
                    ->log("Sale invoice {$sale->invoice_no} contains items sold below cost.");
            }

            return $sale->load(['items.batches', 'customer', 'account']);
        });
    }
}
