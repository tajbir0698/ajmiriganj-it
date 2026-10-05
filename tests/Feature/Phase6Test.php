<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountCategoryType;
use App\Enums\AccountKind;
use App\Enums\BatchSource;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\ReturnSettlement;
use App\Enums\ReturnStatus;
use App\Enums\RoleName;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Filament\Pages\ViewCustomerAccount;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Filament\Resources\SaleReturns\SaleReturnResource;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use App\Services\AccountService;
use App\Services\BusinessFinanceService;
use App\Services\CustomerAccountService;
use App\Services\CustomerPaymentService;
use App\Services\FifoStockService;
use App\Services\PurchaseReturnService;
use App\Services\PurchaseService;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Services\VendorAccountService;
use App\Services\VendorPaymentService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Roles
    $adminRole = Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value]);
    $managerRole = Role::firstOrCreate(['name' => RoleName::MANAGER->value]);

    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@ajmiriganj.test',
    ]);
    $this->admin->assignRole($adminRole);

    $this->manager = User::factory()->create([
        'name' => 'Manager User',
        'email' => 'manager@ajmiriganj.test',
    ]);
    $this->manager->assignRole($managerRole);

    // Seed accounts, categories, settings
    $this->seed(\Database\Seeders\AccountCategorySeeder::class);
    $this->seed(\Database\Seeders\AccountSeeder::class);
    $this->seed(\Database\Seeders\SettingSeeder::class);

    // Provide cash account with sufficient initial balance
    $this->cashAccount = Account::where('name', 'Cash')->first();
    $this->cashAccount->update(['opening_balance' => '100000.00']);

    $this->bkashAccount = Account::where('name', 'bKash')->first();
    $this->bkashAccount->update(['opening_balance' => '50000.00']);

    // Services
    $this->accountService = app(AccountService::class);
    $this->fifoStockService = app(FifoStockService::class);
    $this->vendorAccountService = app(VendorAccountService::class);
    $this->customerAccountService = app(CustomerAccountService::class);
    $this->vendorPaymentService = app(VendorPaymentService::class);
    $this->customerPaymentService = app(CustomerPaymentService::class);
    $this->purchaseService = app(PurchaseService::class);
    $this->purchaseReturnService = app(PurchaseReturnService::class);
    $this->saleService = app(SaleService::class);
    $this->saleReturnService = app(SaleReturnService::class);
    $this->financeService = app(BusinessFinanceService::class);

    // Base master data
    $this->cat = Category::firstOrCreate(['name' => 'Hardware'], ['description' => 'Test']);
    $this->unitPcs = Unit::firstOrCreate(['short_name' => 'Pcs'], ['name' => 'Pieces', 'allow_fractional' => false]);

    $this->product = Product::create([
        'sku' => 'PROD-P6-001',
        'barcode' => '8901234567890',
        'name' => 'Standard Keyboard',
        'category_id' => $this->cat->id,
        'unit_id' => $this->unitPcs->id,
        'last_cost' => '100.0000',
        'sale_price' => '150.00',
        'stock_qty' => '0.000',
        'alert_qty' => '5.000',
        'is_active' => true,
    ]);

    $this->vendor = Vendor::create([
        'name' => 'Apex Technologies',
        'phone' => '01711000001',
        'opening_balance' => '0.00',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'Rahim Chowdhury',
        'phone' => '01811000002',
        'opening_balance' => '0.00',
        'is_active' => true,
    ]);

    // Ensure settings
    Setting::set('credit_sales_enabled', '1', 'boolean');
    Setting::set('payment_account_cash', (string) $this->cashAccount->id, 'integer');

    if (DB::connection()->getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON;');
    }
});

/*
|--------------------------------------------------------------------------
| 1. Vendor Due Formula & Settlement Tests (User Requirement 1)
|--------------------------------------------------------------------------
*/

test('vendor due formula: bill 1000 fully paid, return 400 with refund_received gives vendor due 0 and cash refund 400', function () {
    // 1. Purchase bill 1000 fully paid
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '1000.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    expect($purchase->due_amount)->toBe('0.00')
        ->and($purchase->payment_status->value)->toBe('paid');

    $dueBefore = $this->vendorAccountService->getCurrentDue($this->vendor);
    expect($dueBefore)->toBe('0.00');

    $cashBalBefore = $this->accountService->balance($this->cashAccount);

    // 2. Return 4 units (credit 400) with refund_received
    $batch = $purchase->items->first();
    $return = $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '4.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REFUND_RECEIVED,
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    expect($return->credit_amount)->toBe('400.00')
        ->and($return->refund_received_amount)->toBe('400.00')
        ->and($return->loss_amount)->toBe('0.00');

    // Vendor due must remain 0: 0 + 1000 - 1000 - 400 + 400 = 0
    $dueAfter = $this->vendorAccountService->getCurrentDue($this->vendor);
    expect($dueAfter)->toBe('0.00');

    // Invariant holds
    $this->vendorAccountService->assertDueInvariant($this->vendor);

    // Cash balance increased by 400
    $cashBalAfter = $this->accountService->balance($this->cashAccount);
    expect(bcsub($cashBalAfter, $cashBalBefore, 2))->toBe('400.00');

    // Batch remaining stock decreased by 4 (from 10 to 6)
    expect((string) $batch->fresh()->remaining_qty)->toBe('6.000');
});

test('vendor due formula: bill 1000 with 300 paid, return 400 with refund_received gives due 300 and NO cash refund', function () {
    // 1. Purchase bill 1000 with 300 paid (due = 700)
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '300.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    expect($purchase->due_amount)->toBe('700.00')
        ->and($purchase->payment_status->value)->toBe('partial');

    $dueBefore = $this->vendorAccountService->getCurrentDue($this->vendor);
    expect($dueBefore)->toBe('700.00');

    $cashBalBefore = $this->accountService->balance($this->cashAccount);

    // 2. Return 4 units (credit 400) with refund_received
    $batch = $purchase->items->first();
    $return = $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '4.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REFUND_RECEIVED,
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    expect($return->credit_amount)->toBe('400.00')
        ->and($return->refund_received_amount)->toBe('0.00');

    // Vendor due must be 300: 0 + 1000 - 300 - 400 + 0 = 300
    $dueAfter = $this->vendorAccountService->getCurrentDue($this->vendor);
    expect($dueAfter)->toBe('300.00');

    // Bill due is now 300
    expect((string) $purchase->fresh()->due_amount)->toBe('300.00');

    // Invariant holds
    $this->vendorAccountService->assertDueInvariant($this->vendor);

    // No cash refund was received
    $cashBalAfter = $this->accountService->balance($this->cashAccount);
    expect($cashBalAfter)->toBe($cashBalBefore);
});

test('purchase return with reduce_due_credit reduces bill due and blocks if credit exceeds bill due', function () {
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '800.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    // Total 1000, paid 800 -> due 200
    $batch = $purchase->items->first();

    // Trying to return 3 units (credit 300) with reduce_due_credit when due is only 200 should throw
    expect(fn () => $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '3.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REDUCE_DUE_CREDIT,
        userId: $this->admin->id
    ))->toThrow(InvalidArgumentException::class);

    // Returning 2 units (credit 200) succeeds and clears due
    $return = $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '2.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REDUCE_DUE_CREDIT,
        userId: $this->admin->id
    );

    expect($return->credit_amount)->toBe('200.00')
        ->and($return->refund_received_amount)->toBe('0.00')
        ->and((string) $purchase->fresh()->due_amount)->toBe('0.00')
        ->and($this->vendorAccountService->getCurrentDue($this->vendor))->toBe('0.00');

    $this->vendorAccountService->assertDueInvariant($this->vendor);
});

test('cannot return more quantity than remaining batch stock in purchase return', function () {
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '5.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    $batch = $purchase->items->first();

    expect(fn () => $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '6.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REFUND_RECEIVED,
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    ))->toThrow(\App\Exceptions\InvalidStockOperationException::class);
});

test('cannot cancel purchase if purchase returns exist', function () {
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '5.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    $batch = $purchase->items->first();
    $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '1.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REFUND_RECEIVED,
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    expect(fn () => $this->purchaseService->cancelPurchase($purchase->fresh(), 'Testing cancel with returns', $this->admin))
        ->toThrow(\DomainException::class);
});

/*
|--------------------------------------------------------------------------
| 2. POS Sale & Customer Due (User Requirement 2)
|--------------------------------------------------------------------------
*/

test('SaleService must NOT create a customer_payment or allocation for POS payment; customer due equals due_amount', function () {
    // Add stock: 20 @ 100
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '20.000', '100.0000', BatchSource::OPENING, now());
    });

    // POS sale: 10 @ 150 = 1500. Customer pays 500 at POS, leaving 1000 due.
    $sale = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000'],
        ],
        'paid_amount' => '500.00',
        'received_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    expect($sale->total)->toBe('1500.00')
        ->and($sale->paid_amount)->toBe('500.00')
        ->and($sale->due_amount)->toBe('1000.00')
        ->and($sale->outstanding_due)->toBe('1000.00');

    // Customer payments table must be EMPTY
    expect(CustomerPayment::count())->toBe(0)
        ->and(CustomerPaymentAllocation::count())->toBe(0);

    // Customer due must be exactly equal to sale due_amount (1000.00)
    $customerDue = $this->customerAccountService->getCurrentDue($this->customer);
    expect($customerDue)->toBe('1000.00');

    // Invariant holds
    $this->customerAccountService->assertDueInvariant($this->customer);
});

/*
|--------------------------------------------------------------------------
| 3. BusinessFinanceService & Return Calculations (User Requirement 3)
|--------------------------------------------------------------------------
*/

test('sell 8 @ 150 from batches 5@100 and 10@120 (profit 340), return 3 gives refund 450, cost_restored 360, profit_reversed 90, net profit 250', function () {
    DB::transaction(function () {
        // Batch 1: 5 @ 100
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::PURCHASE, now()->subDays(2));
        // Batch 2: 10 @ 120
        $this->fifoStockService->addBatch($this->product, '10.000', '120.0000', BatchSource::PURCHASE, now()->subDay());
    });

    // Sell 8 @ 150 = 1200. Cost = (5*100) + (3*120) = 860. Net profit = 340. Fully paid.
    $sale = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '8.000'],
        ],
        'paid_amount' => '1200.00',
        'received_amount' => '1200.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    expect((string) $sale->net_profit)->toBe('340.00');
    expect($this->financeService->grossProfit())->toBe('340.00')
        ->and($this->financeService->netProfit())->toBe('340.00');

    // Return 3 units:
    // Restores into MOST RECENTLY consumed batch first (Batch 2: cost 120).
    // Refund = 3 * 150 = 450.
    // Cost restored = 3 * 120 = 360.
    // Profit reversed = 450 - 360 = 90.
    $saleItem = $sale->items->first();
    $return = $this->saleReturnService->createReturn(
        sale: $sale,
        items: [
            ['sale_item_id' => $saleItem->id, 'qty' => '3.000'],
        ],
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    expect($return->refund_amount)->toBe('450.00')
        ->and($return->cost_restored)->toBe('360.00')
        ->and($return->profit_reversed)->toBe('90.00')
        ->and($return->cash_refund)->toBe('450.00');

    // BusinessFinanceService gross_profit and net_profit must be 340 - 90 = 250.00
    expect($this->financeService->grossProfit())->toBe('250.00')
        ->and($this->financeService->netProfit())->toBe('250.00');
});

test('BusinessFinanceService: stock_losses includes purchase_returns.loss_amount and counts on return_date', function () {
    // Purchase 5 @ 100
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '5.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    $batch = $purchase->items->first();

    // Return 2 units with agreed credit price 80 (cost was 100 -> loss is 2 * 20 = 40.00)
    $return = $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '2.000', 'unit_price' => '80.00'],
        ],
        settlement: ReturnSettlement::REFUND_RECEIVED,
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        returnDate: Carbon::parse('2026-06-15'),
        userId: $this->admin->id
    );

    expect($return->loss_amount)->toBe('40.00');

    // Total stock losses across all time
    expect($this->financeService->stockLosses())->toBe('40.00');

    // Date range excluding return date
    $lossesOutside = $this->financeService->stockLosses(
        from: Carbon::parse('2026-07-01'),
        to: Carbon::parse('2026-07-31')
    );
    expect($lossesOutside)->toBe('0.00');

    // Date range including return date
    $lossesInside = $this->financeService->stockLosses(
        from: Carbon::parse('2026-06-01'),
        to: Carbon::parse('2026-06-30')
    );
    expect($lossesInside)->toBe('40.00');
});

test('purchase return where credit_amount > total_cost_removed yields negative loss_amount and decreases stock_losses as a gain', function () {
    // 1. Purchase 5 @ 100 = 500 total cost
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '5.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    $batch = $purchase->items->first();

    // 2. Return 2 units with agreed credit unit price 120 (cost removed = 2*100 = 200, credit = 2*120 = 240)
    // loss_amount = 200 - 240 = -40.00 (gain)
    $return = $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $batch->id, 'qty' => '2.000', 'unit_price' => '120.00'],
        ],
        settlement: ReturnSettlement::REFUND_RECEIVED,
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    expect($return->total_cost_removed)->toBe('200.00')
        ->and($return->credit_amount)->toBe('240.00')
        ->and($return->loss_amount)->toBe('-40.00');

    // BusinessFinanceService stockLosses() reflects negative loss (gain)
    expect($this->financeService->stockLosses())->toBe('-40.00');
});

/*
|--------------------------------------------------------------------------
| 4. Allocation Foreign Keys & Advances (User Requirements 4 & 6)
|--------------------------------------------------------------------------
*/

test('allocation foreign keys use restrictOnDelete, not set null, preventing deletion of purchases with allocations', function () {
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    // Initial allocation was created for 500
    expect(VendorPaymentAllocation::where('purchase_id', $purchase->id)->count())->toBe(1);

    // Verify foreign key constraint definition is restrict on delete
    $vendorFks = \Illuminate\Support\Facades\Schema::getForeignKeys('vendor_payment_allocations');
    $purchaseFk = collect($vendorFks)->first(fn ($fk) => in_array('purchase_id', $fk['columns'], true));
    expect(strtolower($purchaseFk['on_delete']))->toBe('restrict');

    $custFks = \Illuminate\Support\Facades\Schema::getForeignKeys('customer_payment_allocations');
    $saleFk = collect($custFks)->first(fn ($fk) => in_array('sale_id', $fk['columns'], true));
    expect(strtolower($saleFk['on_delete']))->toBe('restrict');
});

test('advance definition: advance equals amount minus allocations; applyAdvance draws from oldest payments, creates is_advance_application=true and NO transaction', function () {
    $this->vendor->update(['opening_balance' => '200.00']);

    // 1. Make payment of 500. Opening balance is 200, so 200 allocated to opening, 300 becomes advance.
    $trxCountBefore = Transaction::count();
    $payment = $this->vendorPaymentService->pay(
        vendor: $this->vendor,
        amount: '500.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        userId: $this->admin->id
    );

    expect(Transaction::count())->toBe($trxCountBefore + 1);

    $allocations = $payment->allocations;
    expect($allocations)->toHaveCount(1)
        ->and($allocations->first()->purchase_id)->toBeNull()
        ->and((string) $allocations->first()->amount)->toBe('200.00')
        ->and($allocations->first()->is_advance_application)->toBeFalse();

    // Vendor advance is 300
    expect($this->vendorAccountService->getTotalAdvance($this->vendor))->toBe('300.00');
    // Global due is -300
    expect($this->vendorAccountService->getCurrentDue($this->vendor))->toBe('-300.00');
    $this->vendorAccountService->assertDueInvariant($this->vendor);

    // 2. Create a new purchase with 250 total, 0 paid
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '0.00',
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '2.500', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    expect($purchase->due_amount)->toBe('250.00');

    // 3. Apply advance to this purchase
    $trxCountBeforeAdvance = Transaction::count();
    $appliedAllocs = $this->vendorPaymentService->applyAdvance(
        vendor: $this->vendor,
        targets: [
            (string) $purchase->id => '250.00',
        ],
        userId: $this->admin->id
    );

    // CRITICAL: NO new transaction created when applying advance!
    expect(Transaction::count())->toBe($trxCountBeforeAdvance);

    expect($appliedAllocs)->toHaveCount(1)
        ->and($appliedAllocs[0]->purchase_id)->toBe($purchase->id)
        ->and((string) $appliedAllocs[0]->amount)->toBe('250.00')
        ->and($appliedAllocs[0]->is_advance_application)->toBeTrue();

    // Purchase is now PAID
    expect((string) $purchase->fresh()->due_amount)->toBe('0.00')
        ->and($purchase->fresh()->payment_status->value)->toBe('paid');

    // Remaining advance is 50
    expect($this->vendorAccountService->getTotalAdvance($this->vendor))->toBe('50.00')
        ->and($this->vendorAccountService->getCurrentDue($this->vendor))->toBe('-50.00');

    $this->vendorAccountService->assertDueInvariant($this->vendor);
});

test('payment reversal restores bill due, creates reversal transaction with allowSystem, and blocks if advance was already consumed', function () {
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '0.00',
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    // Pay 600 allocated to purchase
    $payment = $this->vendorPaymentService->pay(
        vendor: $this->vendor,
        amount: '600.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        userId: $this->admin->id
    );

    expect((string) $purchase->fresh()->due_amount)->toBe('400.00');

    // Reverse payment
    $this->vendorPaymentService->reversePayment(
        payment: $payment,
        reason: 'Duplicate payment mistake',
        userId: $this->admin->id
    );

    // Purchase due is restored to 1000.00
    expect((string) $purchase->fresh()->due_amount)->toBe('1000.00')
        ->and($purchase->fresh()->payment_status->value)->toBe('due');

    // Invariant holds
    $this->vendorAccountService->assertDueInvariant($this->vendor);

    // Verify reversing a payment whose advance was consumed throws
    $p2 = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '0.00',
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '2.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    $advancePayment = $this->vendorPaymentService->pay(
        vendor: $this->vendor,
        amount: '500.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        allocation: 'advance',
        userId: $this->admin->id
    );

    // Apply 200 of the advance to p2
    $this->vendorPaymentService->applyAdvance(
        vendor: $this->vendor,
        targets: [(string) $p2->id => '200.00'],
        userId: $this->admin->id
    );

    // Trying to reverse $advancePayment now should fail because part of it was consumed
    expect(fn () => $this->vendorPaymentService->reversePayment($advancePayment, 'Wrong', $this->admin->id))
        ->toThrow(LogicException::class);
});

/*
|--------------------------------------------------------------------------
| 5. FifoStockService Restore & Sale Returns (User Requirement 5)
|--------------------------------------------------------------------------
*/

test('sale return restores into most recently consumed batch allocation first, capped by qty minus returned_qty', function () {
    DB::transaction(function () {
        // Batch 1: 5 @ 100
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::PURCHASE, now()->subDays(3));
        // Batch 2: 5 @ 120
        $this->fifoStockService->addBatch($this->product, '5.000', '120.0000', BatchSource::PURCHASE, now()->subDays(2));
        // Batch 3: 5 @ 140
        $this->fifoStockService->addBatch($this->product, '5.000', '140.0000', BatchSource::PURCHASE, now()->subDay());
    });

    // Sell 12 @ 200: Consumes Batch 1 (5), Batch 2 (5), Batch 3 (2). Total cost = 500 + 600 + 280 = 1380.
    $sale = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '12.000'],
        ],
        'paid_amount' => '2400.00',
        'received_amount' => '2400.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    $saleItem = $sale->items->first();
    $batches = SaleItemBatch::where('sale_item_id', $saleItem->id)->orderBy('id', 'asc')->get();
    expect($batches)->toHaveCount(3);

    // Return 4 units:
    // Should restore:
    // - 2 units into Batch 3 (capping Batch 3 allocation of 2)
    // - 2 units into Batch 2 (most recently consumed before Batch 3)
    $return = $this->saleReturnService->createReturn(
        sale: $sale,
        items: [
            ['sale_item_id' => $saleItem->id, 'qty' => '4.000'],
        ],
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    $batchesFresh = SaleItemBatch::where('sale_item_id', $saleItem->id)->orderBy('id', 'asc')->get();
    // Batch 1 (id lowest): returned 0
    expect((string) $batchesFresh[0]->returned_qty)->toBe('0.000')
        // Batch 2: returned 2
        ->and((string) $batchesFresh[1]->returned_qty)->toBe('2.000')
        // Batch 3: returned 2 (fully capped)
        ->and((string) $batchesFresh[2]->returned_qty)->toBe('2.000');

    // Cost restored = (2 * 140) + (2 * 120) = 280 + 240 = 520.00
    expect($return->cost_restored)->toBe('520.00');

    // Return remaining 8 units: Batch 2 gets remaining 3, Batch 1 gets 5
    $return2 = $this->saleReturnService->createReturn(
        sale: $sale,
        items: [
            ['sale_item_id' => $saleItem->id, 'qty' => '8.000'],
        ],
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    $batchesFresh2 = SaleItemBatch::where('sale_item_id', $saleItem->id)->orderBy('id', 'asc')->get();
    expect((string) $batchesFresh2[0]->returned_qty)->toBe('5.000')
        ->and((string) $batchesFresh2[1]->returned_qty)->toBe('5.000')
        ->and((string) $batchesFresh2[2]->returned_qty)->toBe('2.000');

    expect($sale->fresh()->return_status)->toBe(ReturnStatus::FULL);
});

test('overall discount share and last-unit rounding exactness on sale return', function () {
    $p2 = Product::create([
        'sku' => 'PROD-P6-002',
        'barcode' => '8901234567891',
        'name' => 'Ergonomic Mouse',
        'category_id' => $this->cat->id,
        'unit_id' => $this->unitPcs->id,
        'last_cost' => '100.0000',
        'sale_price' => '150.00',
        'stock_qty' => '0.000',
        'alert_qty' => '5.000',
        'is_active' => true,
    ]);

    DB::transaction(function () use ($p2) {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
        $this->fifoStockService->addBatch($p2, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    // Sale: 3 pcs product 1 @ 150 = 450, 7 pcs product 2 @ 150 = 1050.
    // Subtotal = 1500. Overall discount = 77.00.
    // Net total = 1423.00.
    $sale = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '3.000'],
            ['product_id' => $p2->id, 'qty' => '7.000'],
        ],
        'discount' => '77.00',
        'paid_amount' => '1423.00',
        'received_amount' => '1423.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    // Return entire sale using returnWholeSale helper
    $return = $this->saleReturnService->returnWholeSale(
        sale: $sale,
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    // Exact refund amount must match net sale total (1423.00)
    expect($return->refund_amount)->toBe('1423.00')
        ->and($sale->fresh()->return_status)->toBe(ReturnStatus::FULL);
});

/*
|--------------------------------------------------------------------------
| 6. Customer Due Collection & Invariant Tests
|--------------------------------------------------------------------------
*/

test('customer due collection: auto-allocates to opening balance first then oldest sale, maintains due invariant', function () {
    $this->customer->update(['opening_balance' => '300.00']);

    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '20.000', '100.0000', BatchSource::OPENING, now());
    });

    // Sale 1: due 500
    $sale1 = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'qty' => '5.000']],
        'paid_amount' => '250.00',
        'received_amount' => '250.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    // Sale 2: due 600
    $sale2 = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'qty' => '4.000']],
        'paid_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    // Global due = 300 (opening) + 500 (sale1) + 600 (sale2) = 1400.00
    expect($this->customerAccountService->getCurrentDue($this->customer))->toBe('1400.00');
    $this->customerAccountService->assertDueInvariant($this->customer);

    // Collect 600: 300 to opening balance, 300 to sale1
    $payment = $this->customerPaymentService->collect(
        customer: $this->customer,
        amount: '600.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        userId: $this->admin->id
    );

    expect($payment->allocations)->toHaveCount(2);

    $openingAlloc = $payment->allocations->whereNull('sale_id')->first();
    $sale1Alloc = $payment->allocations->where('sale_id', $sale1->id)->first();

    expect((string) $openingAlloc->amount)->toBe('300.00')
        ->and((string) $sale1Alloc->amount)->toBe('300.00');

    expect((string) $sale1->fresh()->outstanding_due)->toBe('200.00')
        ->and((string) $sale2->fresh()->outstanding_due)->toBe('600.00');

    expect($this->customerAccountService->getCurrentDue($this->customer))->toBe('800.00');
    $this->customerAccountService->assertDueInvariant($this->customer);
});

test('customer advance application and reversal', function () {
    // Collect 500 when customer has 0 due -> 500 advance
    $payment = $this->customerPaymentService->collect(
        customer: $this->customer,
        amount: '500.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        userId: $this->admin->id
    );

    expect($this->customerAccountService->getTotalAdvance($this->customer))->toBe('500.00')
        ->and($this->customerAccountService->getCurrentDue($this->customer))->toBe('-500.00');
    $this->customerAccountService->assertDueInvariant($this->customer);

    // Reverse payment
    $this->customerPaymentService->reversePayment(
        payment: $payment,
        reason: 'Refunded by mistake',
        userId: $this->admin->id
    );

    expect($this->customerAccountService->getTotalAdvance($this->customer))->toBe('0.00')
        ->and($this->customerAccountService->getCurrentDue($this->customer))->toBe('0.00');
    $this->customerAccountService->assertDueInvariant($this->customer);
});

/*
|--------------------------------------------------------------------------
| 7. Receipts Information Leakage Tests
|--------------------------------------------------------------------------
*/

test('sale return receipt view contains no cost, landed cost, or profit fields', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    $sale = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'qty' => '5.000']],
        'paid_amount' => '750.00',
        'received_amount' => '750.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    $return = $this->saleReturnService->createReturn(
        sale: $sale,
        items: [['sale_item_id' => $sale->items->first()->id, 'qty' => '2.000']],
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    $response = $this->actingAs($this->admin)->get(route('sale-returns.receipt', $return));
    $response->assertOk();

    $content = strtolower($response->getContent());
    expect($content)->not->toContain('unit cost')
        ->and($content)->not->toContain('landed')
        ->and($content)->not->toContain('profit')
        ->and($content)->not->toContain('cost restored');
});

test('customer payment receipt view contains no cost or profit fields', function () {
    $payment = $this->customerPaymentService->collect(
        customer: $this->customer,
        amount: '200.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        userId: $this->admin->id
    );

    $response = $this->actingAs($this->admin)->get(route('customer-payments.receipt', $payment));
    $response->assertOk();

    $content = strtolower($response->getContent());
    expect($content)->not->toContain('cost')
        ->and($content)->not->toContain('profit');
});

/*
|--------------------------------------------------------------------------
| 8. Authorization: Manager gets 403 Forbidden everywhere
|--------------------------------------------------------------------------
*/

test('manager is denied access (403) to Phase 6 operations and routes', function () {
    // Customer account page
    $this->actingAs($this->manager)
        ->get(\App\Filament\Resources\Customers\CustomerResource::getUrl('view', ['record' => $this->customer]))
        ->assertForbidden();

    // Purchase return index & create
    $this->actingAs($this->manager)
        ->get(PurchaseReturnResource::getUrl('index'))
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->get(PurchaseReturnResource::getUrl('create'))
        ->assertForbidden();

    // Sale return index & create
    $this->actingAs($this->manager)
        ->get(SaleReturnResource::getUrl('index'))
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->get(SaleReturnResource::getUrl('create'))
        ->assertForbidden();
});

test('admin can access sale return and purchase return create pages without errors', function () {
    $this->actingAs($this->admin)
        ->get(SaleReturnResource::getUrl('create'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(PurchaseReturnResource::getUrl('create'))
        ->assertOk();
});

test('submitting sale return with excess quantity is stopped before submit with form validation error', function () {
    $this->actingAs($this->admin);

    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    $sale = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '5.000'],
        ],
        'paid_amount' => '750.00',
        'received_amount' => '750.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    $saleItem = $sale->items->first();

    // First return: 5 units (now returnable = 0)
    $this->saleReturnService->createReturn(
        sale: $sale,
        items: [
            ['sale_item_id' => $saleItem->id, 'qty' => '5.000'],
        ],
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    expect((string) $saleItem->fresh()->returnableQty())->toBe('0.000');

    // Attempting to submit another return for 5 units through the Filament form
    \Livewire\Livewire::test(\App\Filament\Resources\SaleReturns\Pages\CreateSaleReturn::class)
        ->fillForm([
            'sale_id' => $sale->id,
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'qty' => '5.000',
                ],
            ],
            'reason' => 'Repeat return attempt',
            'refund_account_id' => $this->cashAccount->id,
            'refund_payment_method' => PaymentMethod::CASH->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['items.0.qty']);
});

/*
|--------------------------------------------------------------------------
| 9. Artisan Commands: dues:check, accounts:check, stock:reconcile
|--------------------------------------------------------------------------
*/

test('dues:check, accounts:check, and stock:reconcile pass cleanly after transactions and returns', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '20.000', '100.0000', BatchSource::OPENING, now());
    });

    // 1. Purchase
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);

    // 2. Sale
    $sale = $this->saleService->createSale([
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '5.000'],
        ],
        'paid_amount' => '500.00',
        'received_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    // 3. Purchase return
    $this->purchaseReturnService->createReturn(
        purchase: $purchase,
        items: [
            ['purchase_item_id' => $purchase->items->first()->id, 'qty' => '2.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REDUCE_DUE_CREDIT,
        userId: $this->admin->id
    );

    // 4. Sale return
    $this->saleReturnService->createReturn(
        sale: $sale,
        items: [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => '1.000'],
        ],
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );

    // Run dues:check
    $this->artisan('dues:check')->assertSuccessful();

    // Run accounts:check
    $this->artisan('accounts:check')->assertSuccessful();

    // Run stock:reconcile
    $this->artisan('stock:reconcile')->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| 10. Random-Mix Invariant Stress Test
|--------------------------------------------------------------------------
*/

test('random mix of sales, payments, returns, and reversals maintains due invariants', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '500.000', '100.0000', BatchSource::OPENING, now());
    });

    $v = Vendor::create(['name' => 'Stress Vendor', 'opening_balance' => '500.00', 'is_active' => true]);
    $c = Customer::create(['name' => 'Stress Customer', 'opening_balance' => '400.00', 'is_active' => true]);

    $this->vendorAccountService->assertDueInvariant($v);
    $this->customerAccountService->assertDueInvariant($c);

    // 1. Purchase
    $p = $this->purchaseService->createPurchase([
        'vendor_id' => $v->id,
        'purchase_date' => today()->toDateString(),
        'paid_amount' => '300.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '20.000', 'unit_cost' => '100.0000'],
        ],
    ], user: $this->admin);
    $this->vendorAccountService->assertDueInvariant($v);

    // 2. Vendor payment
    $vp = $this->vendorPaymentService->pay(
        vendor: $v,
        amount: '800.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        userId: $this->admin->id
    );
    $this->vendorAccountService->assertDueInvariant($v);

    // 3. Purchase return
    $this->purchaseReturnService->createReturn(
        purchase: $p,
        items: [
            ['purchase_item_id' => $p->items->first()->id, 'qty' => '3.000', 'unit_price' => '100.00'],
        ],
        settlement: ReturnSettlement::REDUCE_DUE_CREDIT,
        userId: $this->admin->id
    );
    $this->vendorAccountService->assertDueInvariant($v);

    // 4. Sale
    $s = $this->saleService->createSale([
        'customer_id' => $c->id,
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000'],
        ],
        'paid_amount' => '400.00',
        'received_amount' => '400.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);
    $this->customerAccountService->assertDueInvariant($c);

    // 5. Customer payment
    $cp = $this->customerPaymentService->collect(
        customer: $c,
        amount: '600.00',
        method: PaymentMethod::CASH,
        account: $this->cashAccount,
        date: today()->toDateString(),
        userId: $this->admin->id
    );
    $this->customerAccountService->assertDueInvariant($c);

    // 6. Sale return
    $this->saleReturnService->createReturn(
        sale: $s,
        items: [
            ['sale_item_id' => $s->items->first()->id, 'qty' => '2.000'],
        ],
        refundAccount: $this->cashAccount,
        refundMethod: PaymentMethod::CASH,
        userId: $this->admin->id
    );
    $this->customerAccountService->assertDueInvariant($c);

    // Final checks
    $this->vendorAccountService->assertDueInvariant($v);
    $this->customerAccountService->assertDueInvariant($c);
    $this->artisan('dues:check')->assertSuccessful();
});
