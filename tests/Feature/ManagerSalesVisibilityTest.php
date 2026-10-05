<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\SaleStatus;
use App\Filament\Pages\Reports\SalesReportPage;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Widgets\ManagerTodayStatsWidget;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed essential roles
    Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value]);
    Role::firstOrCreate(['name' => RoleName::MANAGER->value]);

    $this->admin = User::factory()->create([
        'name' => 'Super Admin User',
        'email' => 'admin@ajmiriganj.test',
    ]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->manager = User::factory()->create([
        'name' => 'Shop Manager User',
        'email' => 'manager@ajmiriganj.test',
    ]);
    $this->manager->assignRole(RoleName::MANAGER->value);

    $this->unit = Unit::create(['name' => 'Piece', 'short_name' => 'pcs', 'allow_fractional' => false]);

    $this->product = Product::create([
        'name' => 'TP-Link Router',
        'sku' => 'TPL-01',
        'unit_id' => $this->unit->id,
        'cost_price' => '1200.00',
        'sale_price' => '1800.00',
        'stock_qty' => '10.000',
    ]);

    $this->customer = Customer::create([
        'name' => 'Walk-in Customer',
        'phone' => '01710000000',
    ]);

    // Admin sale
    $this->adminSale = Sale::create([
        'invoice_no' => 'INV-ADMIN-001',
        'customer_id' => $this->customer->id,
        'sale_date' => now()->toDateString(),
        'subtotal' => '1800.00',
        'total' => '1800.00',
        'paid_amount' => '1800.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '600.00',
        'net_profit' => '600.00',
        'created_by' => $this->admin->id,
    ]);

    SaleItem::create([
        'sale_id' => $this->adminSale->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'sku' => $this->product->sku,
        'qty' => '1.000',
        'unit_price' => '1800.00',
        'unit_cost' => '1200.00',
        'line_cost' => '1200.00',
        'line_total' => '1800.00',
        'line_profit' => '600.00',
        'discount' => '0.00',
    ]);

    // Manager sale
    $this->managerSale = Sale::create([
        'invoice_no' => 'INV-MGR-001',
        'customer_id' => $this->customer->id,
        'sale_date' => now()->toDateString(),
        'subtotal' => '1800.00',
        'total' => '1800.00',
        'paid_amount' => '1800.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '600.00',
        'net_profit' => '600.00',
        'created_by' => $this->manager->id,
    ]);

    SaleItem::create([
        'sale_id' => $this->managerSale->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'sku' => $this->product->sku,
        'qty' => '1.000',
        'unit_price' => '1800.00',
        'unit_cost' => '1200.00',
        'line_cost' => '1200.00',
        'line_total' => '1800.00',
        'line_profit' => '600.00',
        'discount' => '0.00',
    ]);

    // Cancelled sale by admin
    $this->cancelledSale = Sale::create([
        'invoice_no' => 'INV-CANCELLED-001',
        'customer_id' => $this->customer->id,
        'sale_date' => now()->toDateString(),
        'subtotal' => '1800.00',
        'total' => '1800.00',
        'paid_amount' => '0.00',
        'due_amount' => '1800.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::CANCELLED,
        'gross_profit' => '0.00',
        'net_profit' => '0.00',
        'created_by' => $this->admin->id,
    ]);
});

test('with "all" visibility: Manager sees Super Admin sale in list, view, receipt, report, print, and dashboard widget', function () {
    Setting::set('manager_sales_visibility', 'all');

    $this->actingAs($this->manager);

    // 1. Sales list: Manager sees Super Admin sale and Cashier column
    $listResponse = $this->get('/admin/sales');
    $listResponse->assertOk()
        ->assertSee($this->adminSale->invoice_no)
        ->assertSee($this->managerSale->invoice_no)
        ->assertSee('Cashier')
        ->assertSee($this->admin->name);

    // 2. Sale view: Manager can view Super Admin sale
    $viewResponse = $this->get("/admin/sales/{$this->adminSale->id}");
    $viewResponse->assertOk()
        ->assertSee($this->adminSale->invoice_no)
        ->assertSee($this->admin->name);

    // 3. Receipt route: Manager can view/reprint Super Admin sale receipt
    $receiptResponse = $this->get("/sales/{$this->adminSale->id}/receipt");
    $receiptResponse->assertOk()
        ->assertSee($this->adminSale->invoice_no)
        ->assertSee($this->admin->name);

    // 4. Manager Sales Report: includes Super Admin sale with Cashier column
    $reportService = app(SalesReportService::class);
    $reportData = $reportService->generate(ReportPeriod::fromPreset('all'), [], $this->manager);
    expect($reportData['is_manager'])->toBeTrue()
        ->and($reportData['rows']->pluck('invoice_no')->all())->toContain($this->adminSale->invoice_no)
        ->and($reportData['rows']->pluck('invoice_no')->all())->toContain($this->managerSale->invoice_no);

    $reportPageResponse = $this->get('/admin/sales-report-page');
    $reportPageResponse->assertOk()
        ->assertSee($this->adminSale->invoice_no)
        ->assertSee('Cashier')
        ->assertSee($this->admin->name);

    // 5. Report Print Route: Manager sees Super Admin sale with Cashier column
    $printResponse = $this->get('/reports/print/sales?period=all');
    $printResponse->assertOk()
        ->assertSee($this->adminSale->invoice_no)
        ->assertSee('Cashier')
        ->assertSee($this->admin->name);

    // 6. Dashboard Widget: Manager sees "Today\'s sales" with count across all cashiers
    Livewire::test(ManagerTodayStatsWidget::class)
        ->assertSee("Today's sales")
        ->assertSee("Today's Invoiced Total")
        ->assertSee("Today's Collected Cash");
});

test('with "own" visibility: Manager does NOT see Super Admin sale and gets 403 on receipt and view', function () {
    Setting::set('manager_sales_visibility', 'own');

    $this->actingAs($this->manager);

    // 1. Sales list: Manager only sees own sale, not admin sale
    $listResponse = $this->get('/admin/sales');
    $listResponse->assertOk()
        ->assertSee($this->managerSale->invoice_no)
        ->assertDontSee($this->adminSale->invoice_no);

    // 2. Sale view: Manager gets 403 on Super Admin sale
    $this->get("/admin/sales/{$this->adminSale->id}")->assertForbidden();

    // 3. Receipt route: Manager gets 403 on Super Admin sale receipt
    $this->get("/sales/{$this->adminSale->id}/receipt")->assertForbidden();

    // But manager CAN view own sale and own receipt
    $this->get("/admin/sales/{$this->managerSale->id}")->assertOk();
    $this->get("/sales/{$this->managerSale->id}/receipt")->assertOk();

    // 4. Sales Report: Manager sees only own sales
    $reportService = app(SalesReportService::class);
    $reportData = $reportService->generate(ReportPeriod::fromPreset('all'), [], $this->manager);
    expect($reportData['rows']->pluck('invoice_no')->all())->not->toContain($this->adminSale->invoice_no)
        ->and($reportData['rows']->pluck('invoice_no')->all())->toContain($this->managerSale->invoice_no);

    // 5. Dashboard Widget: shows "My Sales Today"
    Livewire::test(ManagerTodayStatsWidget::class)
        ->assertSee('My Sales Today')
        ->assertSee('My Invoiced Total')
        ->assertSee('My Collected Cash');
});

test('in BOTH "all" and "own" modes: rendered HTML and Livewire snapshot contain strictly NO cost, profit, margin or FIFO data for Manager', function () {
    foreach (['all', 'own'] as $mode) {
        Setting::set('manager_sales_visibility', $mode);

        $this->actingAs($this->manager);

        // 1. Sales List HTML
        $listContent = $this->get('/admin/sales')->getContent();
        expect($listContent)->not->toContain('net_profit')
            ->not->toContain('gross_profit')
            ->not->toContain('Profit (৳)')
            ->not->toContain('1200.00'); // product unit cost

        // 2. Sale View HTML (on manager sale, and on admin sale if mode is all)
        $salesToTest = $mode === 'all' ? [$this->managerSale, $this->adminSale] : [$this->managerSale];
        foreach ($salesToTest as $sale) {
            $viewContent = $this->get("/admin/sales/{$sale->id}")->getContent();
            expect($viewContent)->not->toContain('Gross Profit')
                ->not->toContain('Net Profit')
                ->not->toContain('Profit & Margin Analysis')
                ->not->toContain('FIFO Cost')
                ->not->toContain('1200.00'); // unit cost
        }

        // 3. Receipt HTML
        $receiptContent = $this->get("/sales/{$this->managerSale->id}/receipt")->getContent();
        expect($receiptContent)->not->toContain('Profit')
            ->not->toContain('Cost')
            ->not->toContain('1200.00');

        // 4. Sales Report HTML & Livewire snapshot
        $reportContent = $this->get('/admin/sales-report-page')->getContent();
        expect($reportContent)->not->toContain('Gross Profit')
            ->not->toContain('Net Profit')
            ->not->toContain('COGS')
            ->not->toContain('Margin');

        // 5. Sales Report Print HTML
        $printContent = $this->get('/reports/print/sales?period=all')->getContent();
        expect($printContent)->not->toContain('Profit')
            ->not->toContain('Margin');
    }
});

test('cancelled sales stay hidden from Manager in BOTH modes and return 403', function () {
    foreach (['all', 'own'] as $mode) {
        Setting::set('manager_sales_visibility', $mode);

        $this->actingAs($this->manager);

        // List does not show cancelled sale
        $listContent = $this->get('/admin/sales')->getContent();
        expect($listContent)->not->toContain($this->cancelledSale->invoice_no);

        // View returns 403
        $this->get("/admin/sales/{$this->cancelledSale->id}")->assertForbidden();

        // Receipt returns 403
        $this->get("/sales/{$this->cancelledSale->id}/receipt")->assertForbidden();

        // Sales report excludes cancelled sale
        $reportService = app(SalesReportService::class);
        $reportData = $reportService->generate(ReportPeriod::fromPreset('all'), [], $this->manager);
        expect($reportData['rows']->pluck('invoice_no')->all())->not->toContain($this->cancelledSale->invoice_no);
    }
});

test('changing manager_sales_visibility setting takes effect immediately via settings cache invalidation', function () {
    // 1. Start with 'own'
    Setting::set('manager_sales_visibility', 'own');
    expect(Setting::get('manager_sales_visibility'))->toBe('own');

    $this->actingAs($this->manager);
    $this->get("/admin/sales/{$this->adminSale->id}")->assertForbidden();

    // 2. Change to 'all'
    Setting::set('manager_sales_visibility', 'all');
    expect(Setting::get('manager_sales_visibility'))->toBe('all');

    // Takes effect immediately: view is now allowed
    $this->get("/admin/sales/{$this->adminSale->id}")->assertOk();

    // 3. Change back to 'own'
    Setting::set('manager_sales_visibility', 'own');
    expect(Setting::get('manager_sales_visibility'))->toBe('own');

    // Immediately forbidden again
    $this->get("/admin/sales/{$this->adminSale->id}")->assertForbidden();
});

test('manager cannot create returns, collect dues, edit or cancel sales', function () {
    $this->actingAs($this->manager);

    // SaleResource has no edit/delete/cancel
    expect(SaleResource::canEdit($this->managerSale))->toBeFalse()
        ->and(SaleResource::canDelete($this->managerSale))->toBeFalse();

    // Cannot access sale returns create page
    $this->get('/admin/sale-returns/create')->assertForbidden();

    // Cannot access customers (where customer balances and dues are managed)
    $this->get('/admin/customers')->assertForbidden();
    $this->get('/admin/customers/create')->assertForbidden();
});
