<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BatchSource;
use App\Enums\PaymentMethod;
use App\Livewire\Pos\PosScreen;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\FifoStockService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected Product $productA;
    protected Product $productB;
    protected Unit $unit;
    protected Category $category;
    protected FifoStockService $fifoStockService;
    protected SaleService $saleService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'Manager']);

        $this->superAdmin = User::factory()->create([
            'email' => 'admin@ajmiriganj.com',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->manager = User::factory()->create([
            'email' => 'manager@ajmiriganj.com',
        ]);
        $this->manager->assignRole('Manager');

        $this->unit = Unit::create([
            'name' => 'Pcs',
            'short_name' => 'Pcs',
            'allow_fractional' => false,
        ]);

        $this->category = Category::create([
            'name' => 'Hardware',
        ]);

        $this->productA = Product::create([
            'name' => 'Keyboard Mechanical',
            'sku' => 'KB-MECH-01',
            'barcode' => '8901234560001',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'cost_price' => '1200.0000',
            'sale_price' => '1800.00',
            'stock_qty' => '0.000',
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'name' => 'Gaming Mouse',
            'sku' => 'MS-GAME-02',
            'barcode' => '8901234560002',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'cost_price' => '600.0000',
            'sale_price' => '950.00',
            'stock_qty' => '0.000',
            'is_active' => true,
        ]);

        // Default financial accounts & settings
        $cashAccount = \App\Models\Account::firstOrCreate(['name' => 'Cash'], [
            'type' => \App\Enums\AccountKind::CASH,
            'opening_balance' => '0.00',
            'is_active' => true,
        ]);
        Setting::set('payment_account_cash', (string) $cashAccount->id, 'integer');
        Setting::set('credit_sales_enabled', '0', 'boolean');
        Setting::set('auto_print_receipt', '0', 'boolean');

        $this->fifoStockService = app(FifoStockService::class);
        $this->saleService = app(SaleService::class);

        // Add opening stock batches
        DB::transaction(function () {
            $this->fifoStockService->addBatch($this->productA, '10.000', '1200.0000', BatchSource::OPENING, now());
            $this->fifoStockService->addBatch($this->productB, '15.000', '600.0000', BatchSource::OPENING, now());
        });
    }

    /**
     * Test 1: Product JSON has NO cost fields for Manager and Super Admin.
     */
    public function test_product_json_has_no_cost_fields_for_both_super_admin_and_manager(): void
    {
        $forbiddenKeys = ['cost_price', 'cost', 'purchase_price', 'avg_cost', 'cogs', 'batches', 'profit'];

        foreach ([$this->superAdmin, $this->manager] as $user) {
            $component = Livewire::actingAs($user)->test(PosScreen::class);
            $safeProducts = $component->get('productsSafe');

            expect($safeProducts)->not->toBeEmpty();

            foreach ($safeProducts as $product) {
                // Assert allowed fields are present
                expect($product)->toHaveKeys([
                    'id', 'name', 'sku', 'barcode', 'category', 'category_id',
                    'unit', 'allow_fractional', 'sale_price', 'stock_qty', 'image'
                ]);

                // Assert forbidden cost fields are strictly absent
                foreach ($forbiddenKeys as $forbiddenKey) {
                    expect(array_key_exists($forbiddenKey, $product))->toBeFalse(
                        "Found forbidden cost key '{$forbiddenKey}' in product catalog for {$user->roles->first()?->name}"
                    );
                }
            }
        }
    }

    /**
     * Test 2: Checkout query-count guard ensures queries are bounded (no N+1).
     */
    public function test_checkout_query_count_is_bounded(): void
    {
        // Create 3 additional products
        for ($i = 3; $i <= 5; $i++) {
            $p = Product::create([
                'name' => "Product {$i}",
                'sku' => "SKU-{$i}",
                'barcode' => "890123456000{$i}",
                'category_id' => $this->category->id,
                'unit_id' => $this->unit->id,
                'cost_price' => '100.0000',
                'sale_price' => '200.00',
                'stock_qty' => '0.000',
                'is_active' => true,
            ]);
            DB::transaction(fn () => $this->fifoStockService->addBatch($p, '10.000', '100.0000', BatchSource::OPENING, now()));
        }

        $customer = Customer::create([
            'name' => 'Regular Buyer',
            'phone' => '01711111111',
            'is_active' => true,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $this->productA->id, 'qty' => '1.000', 'discount' => '0.00', 'unit_price' => '1800.00'],
                ['product_id' => $this->productB->id, 'qty' => '1.000', 'discount' => '0.00', 'unit_price' => '950.00'],
                ['product_id' => 3, 'qty' => '1.000', 'discount' => '0.00', 'unit_price' => '200.00'],
                ['product_id' => 4, 'qty' => '1.000', 'discount' => '0.00', 'unit_price' => '200.00'],
                ['product_id' => 5, 'qty' => '1.000', 'discount' => '0.00', 'unit_price' => '200.00'],
            ],
            'overall_discount' => '0.00',
            'received_amount' => '3350.00',
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $component = Livewire::actingAs($this->manager)->test(PosScreen::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = $component->instance()->completeSale($payload);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($result['success'])->toBeTrue()
            ->and(Sale::count())->toBe(1);

        // Ensure total query count is reasonable and bounded (product verification is a single whereIn query)
        // With transactions, account ledgers, FIFO stock, activity log and sale creation,
        // it should strictly stay under 85 total database queries even with 5 distinct products.
        expect(count($queries))->toBeLessThanOrEqual(85);
    }

    /**
     * Test 3: Price changed between page load and checkout is handled.
     */
    public function test_price_changed_between_page_load_and_checkout_is_handled(): void
    {
        $payload = [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'name' => $this->productA->name,
                    'qty' => '1.000',
                    'discount' => '0.00',
                    'unit_price' => '1800.00', // Client price at page load
                ],
            ],
            'overall_discount' => '0.00',
            'received_amount' => '1800.00',
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ];

        // Another admin changes productA sale price to 2000.00
        $this->productA->update(['sale_price' => '2000.00']);

        $component = Livewire::actingAs($this->manager)->test(PosScreen::class);
        $result = $component->instance()->completeSale($payload);

        expect($result['success'])->toBeFalse()
            ->and($result['code'])->toBe('price_changed')
            ->and($result['new_price'])->toBe('2000.00')
            ->and(Sale::count())->toBe(0); // No sale created
    }

    /**
     * Test 4: Insufficient stock changed between page load and checkout is handled.
     */
    public function test_stock_changed_between_page_load_and_checkout_is_handled(): void
    {
        $payload = [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'name' => $this->productA->name,
                    'qty' => '10.000',
                    'discount' => '0.00',
                    'unit_price' => '1800.00',
                ],
            ],
            'overall_discount' => '0.00',
            'received_amount' => '18000.00',
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ];

        // Another terminal sells 5 pcs, leaving only 5 available
        $this->productA->update(['stock_qty' => '5.000']);

        $component = Livewire::actingAs($this->manager)->test(PosScreen::class);
        $result = $component->instance()->completeSale($payload);

        expect($result['success'])->toBeFalse()
            ->and($result['code'])->toBe('insufficient_stock')
            ->and(Sale::count())->toBe(0);
    }

    /**
     * Test 5: Retry after failure creates ONE sale only and does not double-deduct stock.
     */
    public function test_retry_with_same_idempotency_key_creates_one_sale_only(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'name' => $this->productA->name,
                    'qty' => '2.000',
                    'discount' => '0.00',
                    'unit_price' => '1800.00',
                ],
            ],
            'overall_discount' => '0.00',
            'received_amount' => '3600.00',
            'payment_method' => 'cash',
            'idempotency_key' => $idempotencyKey,
        ];

        $component = Livewire::actingAs($this->manager)->test(PosScreen::class);

        // First checkout attempt
        $res1 = $component->instance()->completeSale($payload);
        expect($res1['success'])->toBeTrue();
        expect(Sale::count())->toBe(1);

        $initialSaleId = $res1['sale_id'];
        $stockAfterFirst = $this->productA->fresh()->stock_qty;
        expect((string) $stockAfterFirst)->toBe('8.000'); // 10 - 2 = 8

        // Retry with the exact same idempotency key (simulating retry after connection drop)
        $res2 = $component->instance()->completeSale($payload);

        expect($res2['success'])->toBeTrue()
            ->and($res2['sale_id'])->toBe($initialSaleId) // Returns same sale!
            ->and(Sale::count())->toBe(1); // Still exactly ONE sale!

        // Stock must NOT be deducted again
        $stockAfterRetry = $this->productA->fresh()->stock_qty;
        expect((string) $stockAfterRetry)->toBe('8.000');
    }
}
