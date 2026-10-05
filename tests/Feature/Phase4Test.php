<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\BatchSource;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Pos\PosScreen;
use App\Models\Account;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\HeldCart;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\FifoStockService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
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

    $this->otherManager = User::factory()->create([
        'name' => 'Other Manager',
        'email' => 'other_manager@ajmiriganj.test',
    ]);
    $this->otherManager->assignRole($managerRole);

    // Default financial accounts
    $this->cashAccount = Account::firstOrCreate(['name' => 'Cash'], [
        'type' => AccountKind::CASH,
        'opening_balance' => '0.00',
        'is_active' => true,
    ]);
    $this->bkashAccount = Account::firstOrCreate(['name' => 'bKash'], [
        'type' => AccountKind::MOBILE_WALLET,
        'opening_balance' => '0.00',
        'is_active' => true,
    ]);

    // Ensure settings
    Setting::set('credit_sales_enabled', '0', 'boolean');
    Setting::set('shop_name', 'Ajmiriganj IT', 'string');
    Setting::set('shop_address', 'Main Bazar, Ajmiriganj, Habiganj', 'string');
    Setting::set('shop_phone', '+8801712345678', 'string');
    Setting::set('receipt_footer', 'Thank you for shopping with us', 'string');
    Setting::set('receipt_width_mm', '80', 'integer');
    Setting::set('auto_print_receipt', '1', 'boolean');
    Setting::set('payment_account_cash', (string) $this->cashAccount->id, 'integer');
    Setting::set('payment_account_bkash', (string) $this->bkashAccount->id, 'integer');

    $this->cat = Category::firstOrCreate(['name' => 'Peripherals'], ['description' => 'Test']);
    $this->unitPcs = Unit::firstOrCreate(['short_name' => 'Pcs'], ['name' => 'Pieces', 'allow_fractional' => false]);
    $this->unitKg = Unit::firstOrCreate(['short_name' => 'Kg'], ['name' => 'Kilogram', 'allow_fractional' => true]);

    $this->fifoStockService = app(FifoStockService::class);
    $this->saleService = app(SaleService::class);
    $this->customerAccountService = app(CustomerAccountService::class);

    $this->product = Product::create([
        'sku' => 'TEST-POS-001',
        'barcode' => '8801234567890',
        'name' => 'Gaming Mouse RGB',
        'category_id' => $this->cat->id,
        'unit_id' => $this->unitPcs->id,
        'last_cost' => '100.0000',
        'sale_price' => '150.00',
        'stock_qty' => '0.000',
        'alert_qty' => '5.000',
        'is_active' => true,
    ]);
});

test('totals with line discounts and overall discount are exact using bcmath', function () {
    // Add stock: 20 @ 100
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '20.000', '100.0000', BatchSource::OPENING, now());
    });

    // 2 items: 2 @ 150 = 300. Line discount: 20.00 -> Line total = 280.00
    // Overall discount: 30.00 -> Net Total = 250.00
    $sale = $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '2.000', 'discount' => '20.00'],
        ],
        'discount' => '30.00',
        'paid_amount' => '250.00',
        'received_amount' => '250.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    expect((string) $sale->subtotal)->toBe('280.00')
        ->and((string) $sale->discount)->toBe('30.00')
        ->and((string) $sale->total)->toBe('250.00')
        ->and((string) $sale->paid_amount)->toBe('250.00')
        ->and((string) $sale->due_amount)->toBe('0.00');
});

test('FIFO profit: batch A 5@100, batch B 10@120, sell 8 at 150 yields line_cost 860.00 and line_profit 340.00 with exact batch allocations', function () {
    DB::transaction(function () {
        // Batch A: 5 @ 100 on Day 1
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::PURCHASE, now()->subDays(2));
        // Batch B: 10 @ 120 on Day 2
        $this->fifoStockService->addBatch($this->product, '10.000', '120.0000', BatchSource::PURCHASE, now()->subDay());
    });

    // Sell 8 @ 150 = 1200.00 gross. Line cost = (5*100) + (3*120) = 860.00. Line profit = 1200 - 860 = 340.00.
    $sale = $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '8.000', 'discount' => '0.00'],
        ],
        'discount' => '40.00', // overall discount
        'paid_amount' => '1160.00',
        'received_amount' => '1200.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    $this->product->refresh();
    expect((string) $this->product->stock_qty)->toBe('7.000'); // 15 - 8 = 7

    $item = $sale->items->first();
    expect((string) $item->unit_cost)->toBe('107.5000') // 860 / 8 = 107.5
        ->and((string) $item->line_cost)->toBe('860.00')
        ->and((string) $item->line_profit)->toBe('340.00')
        ->and((string) $sale->gross_profit)->toBe('340.00')
        ->and((string) $sale->net_profit)->toBe('300.00'); // 340 - 40 = 300

    // Allocations in sale_item_batches
    $batches = $item->batches()->orderBy('id')->get();
    expect($batches)->toHaveCount(2)
        ->and((string) $batches[0]->qty)->toBe('5.000')
        ->and((string) $batches[0]->unit_cost)->toBe('100.0000')
        ->and((string) $batches[1]->qty)->toBe('3.000')
        ->and((string) $batches[1]->unit_cost)->toBe('120.0000');

    // Remaining on batch A is 0, batch B is 7
    $openBatches = PurchaseItem::where('product_id', $this->product->id)->where('remaining_qty', '>', 0)->get();
    expect($openBatches)->toHaveCount(1)
        ->and((string) $openBatches->first()->remaining_qty)->toBe('7.000');

    // Stock movement logged
    $movements = StockMovement::where('product_id', $this->product->id)->where('type', 'sale')->get();
    expect($movements)->toHaveCount(2)
        ->and(bcadd((string) $movements->sum('qty'), '0', 3))->toBe('-8.000');
});

test('insufficient stock cleanly aborts without creating sale, items, transactions, or modifying stock', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    expect(fn () => $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '10.000'],
        ],
        'paid_amount' => '1500.00',
        'received_amount' => '1500.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin))->toThrow(InsufficientStockException::class);

    // Verify atomic cleanliness
    expect(Sale::count())->toBe(0)
        ->and(SaleItem::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);

    $this->product->refresh();
    expect((string) $this->product->stock_qty)->toBe('5.000');
});

test('server ignores tampered unit_price from client and enforces products.sale_price', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    // Client tries to pass unit_price = 10.00 instead of 150.00
    $sale = $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '1.000', 'unit_price' => '10.00'],
        ],
        'paid_amount' => '150.00',
        'received_amount' => '150.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    $item = $sale->items->first();
    expect((string) $item->unit_price)->toBe('150.00')
        ->and((string) $sale->total)->toBe('150.00');
});

test('discounts exceeding line gross or subtotal are rejected', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    // 1 item @ 150.00. Line discount 200.00
    expect(fn () => $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '1.000', 'discount' => '200.00'],
        ],
        'paid_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin))->toThrow(InvalidArgumentException::class);

    // Overall discount 200.00 on 150.00 subtotal
    expect(fn () => $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '1.000', 'discount' => '0.00'],
        ],
        'discount' => '200.00',
        'paid_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin))->toThrow(InvalidArgumentException::class);
});

test('cash sale computes change and creates in transaction in mapped account', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    $sale = $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '1.000'],
        ],
        'paid_amount' => '150.00',
        'received_amount' => '200.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    expect((string) $sale->change_amount)->toBe('50.00')
        ->and((string) $sale->due_amount)->toBe('0.00');

    // Transaction in cash account
    $tx = Transaction::where('reference_type', Sale::class)->where('reference_id', $sale->id)->first();
    expect($tx)->not->toBeNull()
        ->and($tx->account_id)->toBe($this->cashAccount->id)
        ->and((string) $tx->amount)->toBe('150.00')
        ->and($tx->type->value)->toBe('in');
});

test('credit sales rules: disabled credit rejects partial payment; enabled credit creates due and links customer', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    $customer = Customer::create([
        'name' => 'Rahim Store',
        'phone' => '01700000000',
        'opening_balance' => '500.00',
        'is_active' => true,
    ]);

    // 1. Credit disabled -> partial payment rejected
    Setting::set('credit_sales_enabled', '0', 'boolean');
    expect(fn () => $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'customer_id' => $customer->id,
        'paid_amount' => '50.00', // Total is 150
        'received_amount' => '50.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin))->toThrow(InvalidArgumentException::class, 'Credit sales are disabled');

    // 2. Enable credit -> partial payment requires customer
    Setting::set('credit_sales_enabled', '1', 'boolean');
    expect(fn () => $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'customer_id' => null, // No customer!
        'paid_amount' => '50.00',
        'received_amount' => '50.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin))->toThrow(InvalidArgumentException::class, 'registered customer is required');

    // 3. Partial payment with registered customer succeeds
    $sale = $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'customer_id' => $customer->id,
        'paid_amount' => '50.00',
        'received_amount' => '50.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    expect((string) $sale->total)->toBe('150.00')
        ->and((string) $sale->paid_amount)->toBe('50.00')
        ->and((string) $sale->due_amount)->toBe('100.00');

    // Transaction created ONLY for the paid 50.00
    $tx = Transaction::where('reference_type', Sale::class)->where('reference_id', $sale->id)->first();
    expect($tx)->not->toBeNull()
        ->and((string) $tx->amount)->toBe('50.00');

    // 4. Pure credit sale (paid 0.00) creates NO transaction
    $pureCreditSale = $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'customer_id' => $customer->id,
        'paid_amount' => '0.00',
        'received_amount' => '0.00',
    ], $this->admin);

    expect((string) $pureCreditSale->total)->toBe('150.00')
        ->and((string) $pureCreditSale->paid_amount)->toBe('0.00')
        ->and((string) $pureCreditSale->due_amount)->toBe('150.00')
        ->and(Transaction::where('reference_type', Sale::class)->where('reference_id', $pureCreditSale->id)->count())->toBe(0);
});

test('customer due calculation: opening_balance + sum(sale dues) - sum(payments)', function () {
    $customer = Customer::create([
        'name' => 'Karim Enterprise',
        'opening_balance' => '1000.00',
        'is_active' => true,
    ]);

    Setting::set('credit_sales_enabled', '1', 'boolean');
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '20.000', '100.0000', BatchSource::OPENING, now());
    });

    // Sale 1: due 100.00
    $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'customer_id' => $customer->id,
        'paid_amount' => '50.00',
        'received_amount' => '50.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    // Sale 2: due 150.00
    $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'customer_id' => $customer->id,
        'paid_amount' => '0.00',
        'received_amount' => '0.00',
    ], $this->admin);

    // Payment: 200.00
    CustomerPayment::create([
        'payment_no' => 'CPAY-000001',
        'customer_id' => $customer->id,
        'payment_date' => now()->toDateString(),
        'amount' => '200.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'created_by' => $this->admin->id,
    ]);

    // Expected due: 1000.00 + 100.00 + 150.00 - 200.00 = 1050.00
    $due = $this->customerAccountService->getCurrentDue($customer);
    expect($due)->toBe('1050.00');

    // Batch check
    $batchDues = $this->customerAccountService->getDueForCustomers([$customer->id]);
    expect($batchDues[$customer->id])->toBe('1050.00');
});

test('idempotency: same key returns existing sale and deducts stock only once', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    $uuid = 'test-idempotency-key-12345';

    $payload = [
        'items' => [['product_id' => $this->product->id, 'qty' => '2.000']],
        'paid_amount' => '300.00',
        'received_amount' => '300.00',
        'payment_method' => PaymentMethod::CASH,
        'idempotency_key' => $uuid,
    ];

    $sale1 = $this->saleService->createSale($payload, $this->admin);
    $sale2 = $this->saleService->createSale($payload, $this->admin);

    expect($sale1->id)->toBe($sale2->id)
        ->and(Sale::where('idempotency_key', $uuid)->count())->toBe(1);

    $this->product->refresh();
    expect((string) $this->product->stock_qty)->toBe('8.000'); // 10 - 2 = 8 (deducted once)
});

test('invoice numbers are sequential', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    $sale1 = $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'paid_amount' => '150.00',
        'received_amount' => '150.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    $sale2 = $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'paid_amount' => '150.00',
        'received_amount' => '150.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin);

    expect($sale1->invoice_no)->toBe('INV-000001')
        ->and($sale2->invoice_no)->toBe('INV-000002');
});

test('two lines of same product are aggregated for pre-flight stock availability', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    // 3 + 3 = 6 > 5 available -> should abort
    expect(fn () => $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '3.000'],
            ['product_id' => $this->product->id, 'qty' => '3.000'],
        ],
        'paid_amount' => '900.00',
        'received_amount' => '900.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->admin))->toThrow(InsufficientStockException::class);
});

test('pos screen livewire: barcode scan, quantity changes, stock capping, hold and resume', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    $component = Livewire::actingAs($this->manager)
        ->test(PosScreen::class)
        // 1. Scan barcode
        ->set('search', '8801234567890')
        ->call('searchEntered')
        ->assertCount('cart', 1);

    // Verify item in cart
    $cart = $component->get('cart');
    expect($cart[0]['product_id'])->toBe($this->product->id)
        ->and((string) $cart[0]['qty'])->toBe('1.000');

    // 2. Increment quantity to 5
    $component->call('updateQty', 0, '5.000')
        ->assertSet('cart.0.qty', '5.000');

    // 3. Try to exceed stock of 5
    $component->call('updateQty', 0, '6.000')
        ->assertSet('cart.0.qty', '5.000') // capped to 5.000
        ->assertNotSet('errorMessage', null);

    // 4. Hold cart
    $component->call('holdCart')
        ->assertCount('cart', 0);

    expect(HeldCart::where('user_id', $this->manager->id)->count())->toBe(1);

    // 5. Resume cart
    $held = HeldCart::where('user_id', $this->manager->id)->first();
    $component->call('resumeCart', $held->id)
        ->assertCount('cart', 1);

    expect(HeldCart::count())->toBe(0); // deleted on resume
});

test('pos screen complete sale flow: creates sale and resets cart', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    Livewire::actingAs($this->manager)
        ->test(PosScreen::class)
        ->call('addToCart', $this->product->id)
        ->set('receivedAmount', '150.00')
        ->set('paymentMethod', 'cash')
        ->call('completeSale')
        ->assertSet('showSuccessModal', true)
        ->assertCount('cart', 0);

    expect(Sale::count())->toBe(1)
        ->and(Sale::first()->created_by)->toBe($this->manager->id);
});

test('receipt view renders correct details, contains no cost or profit, and enforces authorization', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    $sale = $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'paid_amount' => '150.00',
        'received_amount' => '200.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->manager);

    Setting::set('manager_sales_visibility', 'own');

    // 1. Manager can view own receipt
    $this->actingAs($this->manager);
    $resManager = $this->get("/sales/{$sale->id}/receipt");
    $resManager->assertOk()
        ->assertSee($sale->invoice_no)
        ->assertSee('Gaming Mouse RGB')
        ->assertSee('150.00')
        ->assertDontSee('100.0000') // unit cost
        ->assertDontSee('Profit')
        ->assertDontSee('line_profit');

    // 2. Other Manager cannot view this sale receipt
    $this->actingAs($this->otherManager);
    $this->get("/sales/{$sale->id}/receipt")->assertForbidden();

    // 3. Super Admin can view any receipt
    $this->actingAs($this->admin);
    $this->get("/sales/{$sale->id}/receipt")->assertOk();
});

test('manager security: rendered POS HTML and Livewire snapshot strictly contain no cost data', function () {
    Setting::set('manager_sales_visibility', 'own');

    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '5.000', '100.0000', BatchSource::OPENING, now());
    });

    $this->actingAs($this->manager);

    // 1. GET /pos page
    $response = $this->get('/pos');
    $response->assertOk();
    $content = $response->getContent();

    // Verify cost keywords are strictly absent
    expect(str_contains($content, 'last_cost'))->toBeFalse()
        ->and(str_contains($content, 'unit_cost'))->toBeFalse()
        ->and(str_contains($content, 'line_cost'))->toBeFalse()
        ->and(str_contains($content, '100.0000'))->toBeFalse();

    // 2. Sales list for manager only shows own sales and has no profit column
    $saleOther = $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'paid_amount' => '150.00',
        'received_amount' => '150.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->otherManager);

    $saleMine = $this->saleService->createSale([
        'items' => [['product_id' => $this->product->id, 'qty' => '1.000']],
        'paid_amount' => '150.00',
        'received_amount' => '150.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->manager);

    $salesResponse = $this->get('/admin/sales');
    $salesResponse->assertOk()
        ->assertSee($saleMine->invoice_no)
        ->assertDontSee($saleOther->invoice_no); // Other manager's sale is hidden

    // 3. Manager cannot access Customer Resource, Settings, or Stock Adjustments
    $this->get('/admin/customers')->assertForbidden();
    $this->get('/admin/settings')->assertForbidden();
    $this->get('/admin/stock-adjustments')->assertForbidden();
});

test('architecture test: verifies that only FifoStockService modifies remaining_qty or products.stock_qty', function () {
    $basePath = app_path();
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));

    $violations = [];

    foreach ($files as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $filename = $file->getFilename();
        if ($filename === 'FifoStockService.php' || $filename === 'ReconcileStockCommand.php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        // Check for direct property assignments to stock_qty or remaining_qty
        if (preg_match('/->stock_qty\s*(\+|-)?=/', $content)) {
            $violations[] = $file->getPathname().' writes stock_qty via property assignment!';
        }
        if (preg_match('/->remaining_qty\s*(\+|-)?=/', $content)) {
            $violations[] = $file->getPathname().' writes remaining_qty via property assignment!';
        }

        // Check for query updates, increments, or decrements
        if (preg_match('/(update|increment|decrement)\s*\([^)]*[\'"]stock_qty[\'"]/', $content)) {
            $violations[] = $file->getPathname().' updates/increments stock_qty!';
        }
        if (preg_match('/(update|increment|decrement)\s*\([^)]*[\'"]remaining_qty[\'"]/', $content)) {
            $violations[] = $file->getPathname().' updates/increments remaining_qty!';
        }
    }

    expect($violations)->toBeEmpty();
});

test('pos screen is mobile responsive: includes responsive tab switcher and mobile cart triggers', function () {
    $this->actingAs($this->manager);
    $response = $this->get('/pos');
    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('mobileTab')
        ->and($content)->toContain('Products')
        ->and($content)->toContain('viewport');
});

test('view sale page renders correctly with native infolist and enforces super admin profit visibility', function () {
    DB::transaction(function () {
        $this->fifoStockService->addBatch($this->product, '10.000', '100.0000', BatchSource::OPENING, now());
    });

    $sale = $this->saleService->createSale([
        'items' => [
            ['product_id' => $this->product->id, 'qty' => '1.000', 'discount' => '0.00'],
        ],
        'discount' => '0.00',
        'paid_amount' => '150.00',
        'received_amount' => '150.00',
        'payment_method' => PaymentMethod::CASH,
    ], $this->manager);

    // 1. Super Admin view
    $this->actingAs($this->admin);
    Livewire::test(\App\Filament\Resources\Sales\Pages\ViewSale::class, ['record' => $sale->getKey()])
        ->assertSuccessful()
        ->assertSee($sale->invoice_no)
        ->assertSee('Gaming Mouse RGB')
        ->assertSee('Profit & Margin Analysis')
        ->assertSee('Gross Profit')
        ->assertSee('Net Profit');

    // 2. Manager view
    $this->actingAs($this->manager);
    Livewire::test(\App\Filament\Resources\Sales\Pages\ViewSale::class, ['record' => $sale->getKey()])
        ->assertSuccessful()
        ->assertSee($sale->invoice_no)
        ->assertSee('Gaming Mouse RGB')
        ->assertDontSee('Profit & Margin Analysis')
        ->assertDontSee('Gross Profit')
        ->assertDontSee('Net Profit');
});

