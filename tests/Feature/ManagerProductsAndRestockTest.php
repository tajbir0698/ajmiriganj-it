<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\PaymentMethod;
use App\Enums\RestockRequestStatus;
use App\Enums\RoleName;
use App\Exceptions\InsufficientFundsException;
use App\Filament\Pages\ManagerAddProduct;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\RestockRequests\Pages\CreateRestockRequest;
use App\Filament\Resources\RestockRequests\Pages\ListRestockRequests;
use App\Filament\Resources\RestockRequests\RestockRequestResource;
use App\Livewire\Pos\PosScreen;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\DocumentSequence;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\RestockRequestItem;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\RestockRequestService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed essential roles
    Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value]);
    Role::firstOrCreate(['name' => RoleName::MANAGER->value]);

    $this->admin = User::factory()->create([
        'name' => 'Super Admin',
        'email' => 'admin@ajmiriganj.test',
    ]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->manager = User::factory()->create([
        'name' => 'Shop Manager',
        'email' => 'manager@ajmiriganj.test',
    ]);
    $this->manager->assignRole(RoleName::MANAGER->value);

    $this->category = Category::create(['name' => 'Networking']);
    $this->unit = Unit::create(['name' => 'Piece', 'short_name' => 'pcs', 'allow_fractional' => false]);

    $this->vendor = Vendor::create([
        'name' => 'Tech Supply Co',
        'phone' => '01700000000',
        'is_active' => true,
    ]);

    $this->cashAccount = Account::create([
        'name' => 'Cash in Drawer',
        'type' => AccountKind::CASH,
        'opening_balance' => '50000.00',
        'is_active' => true,
    ]);

    Setting::set('manager_can_add_products', '0');
    Setting::set('manager_can_request_restock', '0');
    Setting::set('default_target_margin_percent', '25');

    Storage::fake('private');
    Storage::fake('public');
});

test('settings switches default to off / disabled', function () {
    expect((bool) Setting::get('manager_can_add_products', false))->toBeFalse();
    expect((bool) Setting::get('manager_can_request_restock', false))->toBeFalse();
});

test('when switches are off, Manager cannot access Add Product or Restock Request routes', function () {
    $this->actingAs($this->manager);

    // Manager Add Product
    $this->get('/admin/manager-add-product')->assertForbidden();

    // Restock Requests
    $this->get('/admin/restock-requests')->assertForbidden();
    $this->get('/admin/restock-requests/create')->assertForbidden();
});

test('when manager_can_add_products is on, Manager can access dedicated Add Product page and submit product', function () {
    Setting::set('manager_can_add_products', '1');
    $this->actingAs($this->manager);

    $this->get('/admin/manager-add-product')->assertSuccessful();

    Livewire::test(ManagerAddProduct::class)
        ->fillForm([
            'name' => 'Wireless Keyboard',
            'sku' => 'WK-001',
            'barcode' => '890123456789',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sale_price' => '1500.00',
            'alert_qty' => '5.000',
            'brand' => 'Logitech',
            'description' => 'Compact wireless keyboard',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('sku', 'WK-001')->first();
    expect($product)->not->toBeNull()
        ->and($product->name)->toBe('Wireless Keyboard')
        ->and((float) $product->stock_qty)->toBe(0.0)
        ->and((bool) $product->is_active)->toBeTrue()
        ->and((bool) $product->needs_review)->toBeTrue()
        ->and($product->created_by)->toBe($this->manager->id);

    // Check PriceHistory created
    $priceHistory = PriceHistory::where('product_id', $product->id)->first();
    expect($priceHistory)->not->toBeNull()
        ->and((float) $priceHistory->new_sale_price)->toBe(1500.00)
        ->and($priceHistory->changed_by)->toBe($this->manager->id);

    // Check Activity Log
    $activity = Activity::where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->latest()
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->manager->id);
});

test('Manager Add Product ignores injected fields like last_cost and stock_qty', function () {
    Setting::set('manager_can_add_products', '1');
    $this->actingAs($this->manager);

    Livewire::test(ManagerAddProduct::class)
        ->fillForm([
            'name' => 'Injected Field Mouse',
            'sku' => 'MS-INJ-01',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sale_price' => '600.00',
            'alert_qty' => '3.000',
        ])
        ->set('data.last_cost', '999.00')
        ->set('data.stock_qty', '500.000')
        ->set('data.needs_review', false)
        ->set('data.created_by', 9999)
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('sku', 'MS-INJ-01')->firstOrFail();
    expect((float) $product->stock_qty)->toBe(0.0)
        ->and((float) $product->last_cost)->toBe(0.0)
        ->and((bool) $product->needs_review)->toBeTrue()
        ->and($product->created_by)->toBe($this->manager->id);
});

test('Manager Add Product warns on duplicate name without blocking', function () {
    Product::create([
        'name' => 'Existing Headphone',
        'sku' => 'HP-EX-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '1200.00',
    ]);

    Setting::set('manager_can_add_products', '1');
    $this->actingAs($this->manager);

    Livewire::test(ManagerAddProduct::class)
        ->fillForm([
            'name' => 'Existing Headphone',
            'sku' => 'HP-NEW-02',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sale_price' => '1300.00',
            'alert_qty' => '5.000',
        ])
        ->call('create')
        ->assertNotified('Duplicate Name Warning')
        ->assertHasNoFormErrors();

    expect(Product::where('sku', 'HP-NEW-02')->exists())->toBeTrue();
});

test('Manager Add Product strictly enforces SKU uniqueness', function () {
    Product::create([
        'name' => 'First Product',
        'sku' => 'UNIQ-SKU-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '500.00',
    ]);

    Setting::set('manager_can_add_products', '1');
    $this->actingAs($this->manager);

    Livewire::test(ManagerAddProduct::class)
        ->fillForm([
            'name' => 'Second Product',
            'sku' => 'UNIQ-SKU-01',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'sale_price' => '550.00',
            'alert_qty' => '5.000',
        ])
        ->call('create')
        ->assertHasFormErrors(['sku' => 'unique']);
});

test('Manager cannot access admin Create Product or edit/delete existing products', function () {
    $product = Product::create([
        'name' => 'Test Monitor',
        'sku' => 'MON-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '15000.00',
    ]);

    Setting::set('manager_can_add_products', '1');
    $this->actingAs($this->manager);

    $this->get('/admin/products/create')->assertForbidden();
    $this->get("/admin/products/{$product->id}/edit")->assertForbidden();
});

test('Requirement 2: products.created_by and needs_review NEVER appear in any Manager payload', function () {
    $product = Product::create([
        'name' => 'Secret Test Router',
        'sku' => 'SEC-01',
        'barcode' => '77889911',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '3200.00',
        'last_cost' => '2500.00',
        'stock_qty' => '15.000',
        'alert_qty' => '2.000',
        'is_active' => true,
        'needs_review' => true,
        'created_by' => $this->manager->id,
    ]);

    // 1. Model toArray() for Manager
    $this->actingAs($this->manager);
    $array = $product->fresh()->toArray();
    expect($array)->not->toHaveKey('created_by')
        ->and($array)->not->toHaveKey('needs_review')
        ->and($array)->not->toHaveKey('last_cost');

    // Model toArray() for Super Admin contains them
    $this->actingAs($this->admin);
    $adminArray = $product->fresh()->toArray();
    expect($adminArray)->toHaveKey('created_by')
        ->and($adminArray)->toHaveKey('needs_review')
        ->and($adminArray)->toHaveKey('last_cost');

    // 2. POS Screen Catalog JSON for Manager
    $this->actingAs($this->manager);
    $posTest = Livewire::test(PosScreen::class);
    $productsSafe = $posTest->get('productsSafe');
    foreach ($productsSafe as $item) {
        expect($item)->not->toHaveKey('created_by')
            ->not->toHaveKey('needs_review')
            ->not->toHaveKey('last_cost');
    }

    // 3. Stock Overview Page HTML and Livewire payload for Manager
    $stockOverviewRes = $this->get('/admin/stock-overview');
    $stockOverviewRes->assertSuccessful();
    $content = $stockOverviewRes->getContent();
    expect($content)->not->toContain('"needs_review"')
        ->not->toContain('"created_by"')
        ->not->toContain('needs_review')
        ->not->toContain('created_by');
});

test('Super Admin sees Needs Review badge on Products and can clear review flag', function () {
    $product = Product::create([
        'name' => 'Unreviewed Speaker',
        'sku' => 'SPK-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '800.00',
        'needs_review' => true,
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->admin);
    expect(\App\Filament\Resources\Products\ProductResource::getNavigationBadge())->toBe('1');

    // Clear review action
    $product->update(['needs_review' => false]);
    expect(\App\Filament\Resources\Products\ProductResource::getNavigationBadge())->toBeNull();
});

test('when manager_can_request_restock is on, Manager can submit restock request and Super Admin receives notification', function () {
    Setting::set('manager_can_request_restock', '1');

    $productA = Product::create([
        'name' => 'USB Cable Type C',
        'sku' => 'USB-C-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '250.00',
        'stock_qty' => '2.000',
    ]);

    $this->actingAs($this->manager);
    $this->get('/admin/restock-requests')->assertSuccessful();
    $this->get('/admin/restock-requests/create')->assertSuccessful();

    Livewire::test(CreateRestockRequest::class)
        ->fillForm([
            'note' => 'Stock is running out quickly for Type C cables',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'qty_requested' => '20.000',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = RestockRequest::where('requested_by', $this->manager->id)->first();
    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(RestockRequestStatus::PENDING)
        ->and($request->items)->toHaveCount(1)
        ->and((float) $request->items->first()->qty_requested)->toBe(20.0);

    // Super Admin received database notification
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $this->admin->id,
        'notifiable_type' => User::class,
    ]);

    // Check Spatie Activity Log
    $activity = Activity::where('subject_type', RestockRequest::class)
        ->where('subject_id', $request->id)
        ->where('description', 'like', '%submitted restock request%')
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('submitted restock request');
});

test('Restock request attachments are private and Manager cannot download them', function () {
    Setting::set('manager_can_request_restock', '1');

    $product = Product::create([
        'name' => 'HDMI Cable',
        'sku' => 'HDMI-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '350.00',
    ]);

    $file = UploadedFile::fake()->create('private_memo.pdf', 50, 'application/pdf');

    $request = app(RestockRequestService::class)->createRequest([
        'note' => 'Attachment privacy test',
        'items' => [
            ['product_id' => $product->id, 'qty_requested' => '10.000'],
        ],
    ], [$file], $this->manager);

    $attachment = $request->attachments()->first();
    expect($attachment)->not->toBeNull();

    // Manager download attempt is 403 Forbidden
    $this->actingAs($this->manager);
    $this->get(route('admin.attachments.download', $attachment->id))->assertForbidden();

    // Super Admin download attempt is successful
    $this->actingAs($this->admin);
    $this->get(route('admin.attachments.download', $attachment->id))->assertSuccessful();
});

test('Manager can cancel their own pending request, but cannot cancel non-pending or others requests', function () {
    Setting::set('manager_can_request_restock', '1');

    $product = Product::create([
        'name' => 'VGA Cable',
        'sku' => 'VGA-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '150.00',
    ]);

    $service = app(RestockRequestService::class);

    $req = $service->createRequest([
        'note' => 'Mistake order',
        'items' => [['product_id' => $product->id, 'qty_requested' => '5.000']],
    ], [], $this->manager);

    // Cancel own pending request
    $service->cancelRequest($req, $this->manager);
    expect($req->fresh()->status)->toBe(RestockRequestStatus::CANCELLED);

    // Trying to cancel again throws exception
    expect(fn () => $service->cancelRequest($req->fresh(), $this->manager))
        ->toThrow(DomainException::class, 'Only pending restock requests can be cancelled.');

    // Another manager trying to cancel
    $otherManager = User::factory()->create();
    $otherManager->assignRole(RoleName::MANAGER->value);

    $req2 = $service->createRequest([
        'note' => 'Another order',
        'items' => [['product_id' => $product->id, 'qty_requested' => '2.000']],
    ], [], $this->manager);

    expect(fn () => $service->cancelRequest($req2, $otherManager))
        ->toThrow(DomainException::class, 'You can only cancel your own pending restock requests.');
});

test('Super Admin can reject restock request with a reason', function () {
    $product = Product::create([
        'name' => 'Ethernet Crimper',
        'sku' => 'CRM-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '450.00',
    ]);

    $service = app(RestockRequestService::class);
    $req = $service->createRequest([
        'note' => 'Need crimper tools',
        'items' => [['product_id' => $product->id, 'qty_requested' => '3.000']],
    ], [], $this->manager);

    $service->rejectRequest($req, 'Over budget this month', $this->admin);

    $reqFresh = $req->fresh();
    expect($reqFresh->status)->toBe(RestockRequestStatus::REJECTED)
        ->and($reqFresh->review_note)->toBe('Over budget this month')
        ->and($reqFresh->reviewed_by)->toBe($this->admin->id)
        ->and($reqFresh->reviewed_at)->not->toBeNull();
});

test('Requirement 3: Atomic approval links unique purchase_id, calculates qty_approved, and leaves request pending on failure', function () {
    $product1 = Product::create([
        'name' => 'CAT6 Cable Box',
        'sku' => 'CAT6-BOX',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '6500.00',
        'last_cost' => '4800.00',
        'stock_qty' => '0.000',
    ]);

    $product2 = Product::create([
        'name' => 'RJ45 Connectors 100pk',
        'sku' => 'RJ45-100',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '400.00',
        'last_cost' => '250.00',
        'stock_qty' => '0.000',
    ]);

    $service = app(RestockRequestService::class);
    $req = $service->createRequest([
        'note' => 'Monthly cabling restocking',
        'items' => [
            ['product_id' => $product1->id, 'qty_requested' => '10.000'],
            ['product_id' => $product2->id, 'qty_requested' => '50.000'],
        ],
    ], [], $this->manager);

    $this->actingAs($this->admin);
    expect(RestockRequestResource::getNavigationBadge())->toBe('1');

    // 1. Test failure case: If purchase creation fails due to insufficient funds, request remains PENDING
    $this->cashAccount->update(['opening_balance' => '5000.00']);

    $failingPurchaseData = [
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-05',
        'items' => [
            [
                'product_id' => $product1->id,
                'qty' => '10.000',
                'unit_cost' => '4800.00', // Total 48,000
                'new_sale_price' => '6500.00',
            ],
        ],
        'paid_amount' => '48000.00', // Valid <= total, but exceeds cash account balance (5,000)
        'payment_method' => PaymentMethod::CASH->value,
        'account_id' => $this->cashAccount->id,
    ];

    expect(function () use ($service, $req, $failingPurchaseData) {
        $service->approveAndCreatePurchase($req, $failingPurchaseData, [], $this->admin);
    })->toThrow(InsufficientFundsException::class);

    // Request is still PENDING!
    expect($req->fresh()->status)->toBe(RestockRequestStatus::PENDING)
        ->and($req->fresh()->purchase_id)->toBeNull();

    // 2. Test successful atomic approval:
    // Give cash account enough funds
    $this->cashAccount->update(['opening_balance' => '50000.00']);

    // Super Admin approves product1 with 8.000 qty, but removes product2 (qty becomes 0)
    $successfulPurchaseData = [
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-05',
        'vendor_invoice_no' => 'BILL-CAB-01',
        'items' => [
            [
                'product_id' => $product1->id,
                'qty' => '8.000',
                'unit_cost' => '4800.00',
                'new_sale_price' => '6500.00',
            ],
            // Product2 is omitted (removed by admin)
        ],
        'paid_amount' => '10000.00',
        'payment_method' => PaymentMethod::CASH->value,
        'account_id' => $this->cashAccount->id,
    ];

    $purchase = $service->approveAndCreatePurchase($req, $successfulPurchaseData, [], $this->admin);

    $reqFresh = $req->fresh(['items']);
    expect($reqFresh->status)->toBe(RestockRequestStatus::APPROVED)
        ->and($reqFresh->purchase_id)->toBe($purchase->id)
        ->and($reqFresh->reviewed_by)->toBe($this->admin->id)
        ->and($reqFresh->reviewed_at)->not->toBeNull();

    // Verify qty_approved per item: product1 = 8.000, product2 = 0.000
    $item1 = $reqFresh->items->where('product_id', $product1->id)->first();
    $item2 = $reqFresh->items->where('product_id', $product2->id)->first();

    expect((float) $item1->qty_approved)->toBe(8.0)
        ->and((float) $item2->qty_approved)->toBe(0.0);

    // Pending badge count now decremented to 0
    expect(RestockRequestResource::getNavigationBadge())->toBeNull();
});

test('CreatePurchase form mounts securely and pre-fills items from restock_request_id on server', function () {
    $product = Product::create([
        'name' => 'Fiber Patch Cord',
        'sku' => 'FPC-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '200.00',
        'last_cost' => '120.00',
        'stock_qty' => '0.000',
    ]);

    $req = app(RestockRequestService::class)->createRequest([
        'note' => 'Fiber cables required',
        'items' => [
            ['product_id' => $product->id, 'qty_requested' => '15.000'],
        ],
    ], [], $this->manager);

    $this->actingAs($this->admin);

    Livewire::withQueryParams(['restock_request_id' => $req->id])
        ->test(CreatePurchase::class)
        ->assertSet('restockRequestId', $req->id)
        ->assertFormSet([
            'items.0.product_id' => $product->id,
            'items.0.qty' => 15.0,
        ]);
});

test('Manager and Super Admin can view approved restock request infolist without errors', function () {
    Setting::set('manager_can_request_restock', '1');

    $product = Product::create([
        'name' => 'View Test Switch',
        'sku' => 'VTS-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '1200.00',
        'last_cost' => '800.00',
        'stock_qty' => '0.000',
    ]);

    $service = app(RestockRequestService::class);
    $req = $service->createRequest([
        'note' => 'Please restock switches',
        'items' => [
            ['product_id' => $product->id, 'qty_requested' => '5.000'],
        ],
    ], [], $this->manager);

    // Approve the request
    $purchaseData = [
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-05',
        'items' => [
            [
                'product_id' => $product->id,
                'qty' => '5.000',
                'unit_cost' => '800.00',
                'new_sale_price' => '1200.00',
            ],
        ],
    ];
    $service->approveAndCreatePurchase($req, $purchaseData, [], $this->admin);

    // 1. Manager views the approved request
    $this->actingAs($this->manager);
    $responseManager = $this->get("/admin/restock-requests/{$req->id}");
    $responseManager->assertSuccessful();

    // 2. Super Admin views the approved request
    $this->actingAs($this->admin);
    $responseAdmin = $this->get("/admin/restock-requests/{$req->id}");
    $responseAdmin->assertSuccessful();
});
