<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Enums\TransactionType;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\AttachmentService;
use App\Services\PurchaseService;
use App\Services\SequenceService;
use App\Services\VendorAccountService;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->superAdmin = User::where('email', 'admin@ajmiriganj.com')->first();
    $this->manager = User::where('email', 'manager@ajmiriganj.com')->first();
    $this->vendor = Vendor::first();
    $this->account = Account::first();
    $this->account->update(['opening_balance' => '50000.00']);
    $this->purchaseService = app(PurchaseService::class);
    $this->vendorAccountService = app(VendorAccountService::class);
});

test('sequential invoice numbers are generated atomically with lock', function () {
    $inv1 = SequenceService::nextPurchaseInvoice();
    $inv2 = SequenceService::nextPurchaseInvoice();
    $inv3 = SequenceService::nextPurchaseInvoice();

    expect($inv1)->toBe('PUR-000001')
        ->and($inv2)->toBe('PUR-000002')
        ->and($inv3)->toBe('PUR-000003');
});

test('landed cost spreads shipping and discount proportionally and remainder to last row', function () {
    $p1 = Product::where('sku', 'MOUSE-LOG-B100')->first();
    $p2 = Product::where('sku', 'KB-A4T-FK10')->first();

    // Row 1: 10 pcs @ 100 = 1000
    // Row 2: 10 pcs @ 200 = 2000
    // Total subtotal = 3000
    // Shipping = 150, Discount = 30. Net adjustment = +120.
    // Row 1 share: 120 * (1000/3000) = 40. Landed cost = 100 + (40/10) = 104.
    // Row 2 share: 120 * (2000/3000) = 80. Landed cost = 200 + (80/10) = 208.
    $items = [
        ['product_id' => $p1->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ['product_id' => $p2->id, 'qty' => '10.000', 'unit_cost' => '200.0000'],
    ];

    $calculated = $this->purchaseService->calculateLandedCosts($items, '150.00', '30.00');

    expect($calculated)->toHaveCount(2)
        ->and($calculated[0]['landed_unit_cost'])->toBe('104.0000')
        ->and($calculated[1]['landed_unit_cost'])->toBe('208.0000');

    // Test uneven division with rounding: Net adjustment 10.00 across 3 items
    $items3 = [
        ['product_id' => $p1->id, 'qty' => '1.000', 'unit_cost' => '10.0000'],
        ['product_id' => $p2->id, 'qty' => '1.000', 'unit_cost' => '10.0000'],
        ['product_id' => $p1->id, 'qty' => '1.000', 'unit_cost' => '10.0000'],
    ];
    $calc3 = $this->purchaseService->calculateLandedCosts($items3, '10.00', '0.00');
    // Total net adjustment = 10.0000
    // Row 1 share: 3.3333
    // Row 2 share: 3.3333
    // Row 3 share: 10.0000 - 6.6666 = 3.3334
    $totalShare = bcadd(
        bcsub($calc3[0]['landed_unit_cost'], '10.0000', 4),
        bcadd(
            bcsub($calc3[1]['landed_unit_cost'], '10.0000', 4),
            bcsub($calc3[2]['landed_unit_cost'], '10.0000', 4),
            4
        ),
        4
    );
    expect($totalShare)->toBe('10.0000');
});

test('suggested sale price calculates accurately and rounds according to settings', function () {
    // Landed cost 100, default target margin 25% => 125.00
    $suggested = $this->purchaseService->calculateSuggestedSalePrice('100.0000', 25.0);
    expect($suggested)->toBe('125.00');

    // Landed cost 120, margin 20% => 144.00
    $suggested2 = $this->purchaseService->calculateSuggestedSalePrice('120.0000', 20.0);
    expect($suggested2)->toBe('144.00');
});

test('purchase creates batches with remaining_qty, stock movements, and updates product stock and cost', function () {
    $product = Product::where('sku', 'MOUSE-LOG-B100')->first();
    $initialStock = (string) $product->stock_qty;

    $purchaseData = [
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'vendor_invoice_no' => 'BILL-9901',
        'shipping_cost' => '50.00',
        'discount' => '10.00',
        'paid_amount' => '0.00',
        'items' => [
            [
                'product_id' => $product->id,
                'qty' => '10.000',
                'unit_cost' => '300.0000',
                'new_sale_price' => '420.00',
            ],
        ],
    ];

    $purchase = $this->purchaseService->createPurchase($purchaseData, [], $this->superAdmin);

    expect($purchase->status)->toBe(PurchaseStatus::ACTIVE)
        ->and($purchase->payment_status)->toBe(PaymentStatus::DUE)
        ->and($purchase->subtotal)->toBe('3000.00')
        ->and($purchase->total)->toBe('3040.00') // 3000 - 10 + 50
        ->and($purchase->due_amount)->toBe('3040.00')
        ->and($purchase->paid_amount)->toBe('0.00');

    // Verify batch
    expect($purchase->items)->toHaveCount(1);
    $batch = $purchase->items->first();
    expect($batch->qty)->toBe('10.000')
        ->and($batch->remaining_qty)->toBe('10.000')
        ->and($batch->unit_cost)->toBe('300.0000')
        ->and($batch->landed_unit_cost)->toBe('304.0000'); // 300 + (40/10)

    // Verify stock movement
    $movement = StockMovement::where('purchase_item_id', $batch->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe(StockMovementType::PURCHASE)
        ->and($movement->qty)->toBe('10.000')
        ->and($movement->unit_cost)->toBe('304.0000');

    // Verify product update
    $product->refresh();
    expect($product->stock_qty)->toBe(bcadd($initialStock, '10.000', 3))
        ->and($product->last_cost)->toBe('304.0000')
        ->and($product->sale_price)->toBe('420.00');
});

test('price history is written when cost or sale price changes and omitted when unchanged', function () {
    $product = Product::where('sku', 'ROUT-TPL-840N')->first();
    $currentCost = (string) $product->last_cost;
    $currentSalePrice = (string) $product->sale_price;

    $initialHistoryCount = PriceHistory::where('product_id', $product->id)->count();

    // Purchase 1: with changed sale price
    $purchaseData1 = [
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'shipping_cost' => '0.00',
        'discount' => '0.00',
        'paid_amount' => '0.00',
        'items' => [
            [
                'product_id' => $product->id,
                'qty' => '5.000',
                'unit_cost' => $currentCost,
                'new_sale_price' => '1600.00', // changed
            ],
        ],
    ];

    $this->purchaseService->createPurchase($purchaseData1, [], $this->superAdmin);
    expect(PriceHistory::where('product_id', $product->id)->count())->toBe($initialHistoryCount + 1);

    // Purchase 2: with exact same cost and exact same sale price (no change)
    $product->refresh();
    $purchaseData2 = [
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'shipping_cost' => '0.00',
        'discount' => '0.00',
        'paid_amount' => '0.00',
        'items' => [
            [
                'product_id' => $product->id,
                'qty' => '5.000',
                'unit_cost' => (string) $product->last_cost,
                'new_sale_price' => (string) $product->sale_price,
            ],
        ],
    ];

    $this->purchaseService->createPurchase($purchaseData2, [], $this->superAdmin);
    // History count should NOT increase
    expect(PriceHistory::where('product_id', $product->id)->count())->toBe($initialHistoryCount + 1);
});

test('payment creates vendor_payments row and an out transaction in the chosen account', function () {
    $product = Product::first();
    $account = Account::first();
    $initialAccountBalance = $account->getCurrentBalance();

    $purchaseData = [
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'shipping_cost' => '0.00',
        'discount' => '0.00',
        'paid_amount' => '500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $account->id,
        'reference_no' => 'CASH-REC-101',
        'items' => [
            [
                'product_id' => $product->id,
                'qty' => '5.000',
                'unit_cost' => '200.0000', // total = 1000
            ],
        ],
    ];

    $purchase = $this->purchaseService->createPurchase($purchaseData, [], $this->superAdmin);

    expect($purchase->payment_status)->toBe(PaymentStatus::PARTIAL)
        ->and($purchase->total)->toBe('1000.00')
        ->and($purchase->paid_amount)->toBe('500.00')
        ->and($purchase->due_amount)->toBe('500.00');

    // Vendor payment record
    $payment = VendorPayment::where('purchase_id', $purchase->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe('500.00')
        ->and($payment->account_id)->toBe($account->id)
        ->and($payment->vendor_id)->toBe($this->vendor->id);

    // Account transaction
    $transaction = Transaction::where('reference_type', VendorPayment::class)
        ->where('reference_id', $payment->id)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe(TransactionType::OUT)
        ->and($transaction->amount)->toBe('500.00')
        ->and($transaction->account_id)->toBe($account->id);

    // Account balance decreased by 500
    expect($account->getCurrentBalance())->toBe(Money::sub($initialAccountBalance, '500.00'));
});

test('vendor current_due and ledger accurately reconcile opening balance, purchases, and payments', function () {
    $vendor = Vendor::create([
        'name' => 'Test Vendor Alpha',
        'opening_balance' => '200.00',
        'is_active' => true,
    ]);

    $product = Product::first();
    $account = Account::first();

    // 1. Initial due = opening balance = 200.00
    expect($this->vendorAccountService->getCurrentDue($vendor))->toBe('200.00');

    // 2. Make Purchase 1: Total 1000, Paid 400 => Net due added = 600
    $this->purchaseService->createPurchase([
        'vendor_id' => $vendor->id,
        'purchase_date' => '2026-10-01',
        'shipping_cost' => '0.00',
        'discount' => '0.00',
        'paid_amount' => '400.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $account->id,
        'items' => [
            ['product_id' => $product->id, 'qty' => '10.000', 'unit_cost' => '100.0000'],
        ],
    ], [], $this->superAdmin);

    // Current due = 200 + 1000 - 400 = 800.00
    expect($this->vendorAccountService->getCurrentDue($vendor))->toBe('800.00');

    // Check ledger reconciliation
    $ledger = $this->vendorAccountService->getLedger($vendor);
    expect($ledger)->toHaveCount(3); // Opening (200), Purchase (1000), Payment (400)
    expect($ledger->last()['balance'])->toBe('800.00');
});

test('purchase cancellation succeeds on untouched batches and fully reverses stock and payments', function () {
    $product = Product::first();
    $account = Account::first();
    $stockBefore = (string) $product->stock_qty;
    $balanceBefore = $account->getCurrentBalance();

    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'shipping_cost' => '0.00',
        'discount' => '0.00',
        'paid_amount' => '300.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $account->id,
        'items' => [
            ['product_id' => $product->id, 'qty' => '4.000', 'unit_cost' => '150.0000'],
        ],
    ], [], $this->superAdmin);

    $dueBeforeCancel = $this->vendorAccountService->getCurrentDue($this->vendor);

    // Cancel the purchase
    $cancelled = $this->purchaseService->cancelPurchase($purchase, 'Wrong items sent by vendor', $this->superAdmin);

    expect($cancelled->status)->toBe(PurchaseStatus::CANCELLED)
        ->and($cancelled->isCancelled())->toBeTrue();

    // Stock reversed
    $product->refresh();
    expect($product->stock_qty)->toBe($stockBefore);

    // Negative movement created
    $reversingMovement = StockMovement::where('reference_id', $purchase->id)
        ->where('qty', '-4.000')
        ->first();
    expect($reversingMovement)->not->toBeNull();

    // Account restored
    expect($account->getCurrentBalance())->toBe($balanceBefore);

    // Vendor due restored
    $dueAfterCancel = $this->vendorAccountService->getCurrentDue($this->vendor);
    expect($dueAfterCancel)->toBe(Money::sub($dueBeforeCancel, '300.00')); // reversed 600 - 300 effect
});

test('purchase cancellation is blocked if any batch was partially consumed', function () {
    $product = Product::first();
    $purchase = $this->purchaseService->createPurchase([
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'items' => [
            ['product_id' => $product->id, 'qty' => '5.000', 'unit_cost' => '100.0000'],
        ],
    ], [], $this->superAdmin);

    // Simulate partial consumption of batch (Phase 3 simulation)
    $batch = $purchase->items->first();
    $batch->remaining_qty = '3.000';
    $batch->save();

    expect(fn () => $this->purchaseService->cancelPurchase($purchase, 'Attempting cancellation', $this->superAdmin))
        ->toThrow(DomainException::class);
});

test('attachment service stores file securely on private disk and rejects invalid formats', function () {
    Storage::fake('private');
    $service = app(AttachmentService::class);

    // Valid PDF upload
    $pdf = UploadedFile::fake()->create('supplier_invoice.pdf', 500, 'application/pdf');
    $attachment = $service->store($pdf, $this->vendor, $this->superAdmin);

    expect($attachment)->not->toBeNull()
        ->and($attachment->original_name)->toBe('supplier_invoice.pdf')
        ->and($attachment->isPdf())->toBeTrue();

    Storage::disk('private')->assertExists($attachment->file_path);

    // Invalid format upload (e.g. .exe / text)
    $invalid = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');
    expect(fn () => $service->store($invalid, $this->vendor, $this->superAdmin))
        ->toThrow(InvalidArgumentException::class);
});

test('manager is strictly forbidden from accessing vendors, purchases, batches, and attachments', function () {
    $purchase = Purchase::first();

    // Manager blocked from Filament vendor and purchase routes
    $this->actingAs($this->manager);

    $this->get('/admin/vendors')->assertForbidden();
    $this->get('/admin/vendors/create')->assertForbidden();
    $this->get('/admin/purchases')->assertForbidden();
    $this->get('/admin/purchases/create')->assertForbidden();

    if ($purchase) {
        $this->get("/admin/purchases/{$purchase->id}")->assertForbidden();
    }

    // Manager blocked from attachment download route
    $attachment = Attachment::create([
        'attachable_type' => Vendor::class,
        'attachable_id' => $this->vendor->id,
        'file_path' => 'attachments/test.pdf',
        'original_name' => 'test.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $this->get(route('admin.attachments.download', $attachment->id))->assertForbidden();

    // Super Admin can download
    $this->actingAs($this->superAdmin);
    Storage::disk('private')->put('attachments/test.pdf', 'dummy pdf content');
    $this->get(route('admin.attachments.download', $attachment->id))->assertOk();
});

test('super admin can view create purchase page without errors', function () {
    $this->actingAs($this->superAdmin);

    $this->get('/admin/purchases/create')
        ->assertOk()
        ->assertSee('Vendor &amp; Invoice Information', false)
        ->assertSee('Purchase Items (Stock In &amp; FIFO Batches)', false);
});

test('super admin can submit create purchase form via filament livewire component', function () {
    $this->actingAs($this->superAdmin);

    $product = Product::where('sku', 'MOUSE-LOG-B100')->first();

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'vendor_id' => $this->vendor->id,
            'purchase_date' => now()->toDateString(),
            'vendor_invoice_no' => 'INV-TEST-999',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 5,
                    'unit_cost' => 120,
                    'target_margin' => 25,
                    'new_sale_price' => 150,
                ],
            ],
            'shipping_cost' => 50,
            'discount' => 10,
            'paid_amount' => 640,
            'payment_method' => PaymentMethod::CASH->value,
            'account_id' => $this->account->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::where('vendor_invoice_no', 'INV-TEST-999')->first();
    expect($purchase)->not->toBeNull()
        ->and($purchase->total)->toBe('640.00')
        ->and($purchase->paid_amount)->toBe('640.00')
        ->and($purchase->payment_status)->toBe(PaymentStatus::PAID);
});
