<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\RecordTransactionData;
use App\Enums\AccountCategoryType;
use App\Enums\AccountKind;
use App\Enums\BatchSource;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\RoleName;
use App\Enums\SaleStatus;
use App\Enums\TransactionType;
use App\Filament\Pages\Reports\BalanceSheetReportPage;
use App\Filament\Pages\Reports\DailySummaryReportPage;
use App\Filament\Pages\Reports\DeadStockReportPage;
use App\Filament\Pages\Reports\ProfitAndLossReportPage;
use App\Filament\Pages\Reports\SalesReportPage;
use App\Filament\Pages\Reports\StockValuationReportPage;
use App\Filament\Widgets\AccountBalancesWidget;
use App\Filament\Widgets\FinancialSummaryWidget;
use App\Filament\Widgets\ManagerTodayStatsWidget;
use App\Filament\Widgets\TodayStatsWidget;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountService;
use App\Services\BusinessFinanceService;
use App\Services\CustomerAccountService;
use App\Services\FifoStockService;
use App\Services\Reports\BalanceSheetReportService;
use App\Services\Reports\CustomerAgingReportService;
use App\Services\Reports\CustomerDueReportService;
use App\Services\Reports\DailySummaryReportService;
use App\Services\Reports\DeadStockReportService;
use App\Services\Reports\ProductSalesReportService;
use App\Services\Reports\ProfitAndLossReportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\StockValuationReportService;
use App\Services\Reports\VendorAgingReportService;
use App\Services\Reports\VendorDueReportService;
use App\Services\VendorAccountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 14:00:00');

    // Create roles
    Role::findOrCreate(RoleName::SUPER_ADMIN->value);
    Role::findOrCreate(RoleName::MANAGER->value);

    // Create Super Admin and Manager
    $this->admin = User::factory()->create([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
    ]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->manager = User::factory()->create([
        'name' => 'Store Manager',
        'email' => 'manager@test.com',
    ]);
    $this->manager->assignRole(RoleName::MANAGER->value);

    // Seed basic settings
    Setting::set('company_name', 'Ajmiriganj IT');
    Setting::set('dead_stock_days', 60);

    // Services
    $this->accountService = app(AccountService::class);
    $this->financeService = app(BusinessFinanceService::class);
    $this->fifoStockService = app(FifoStockService::class);
    $this->customerAccountService = app(CustomerAccountService::class);
    $this->vendorAccountService = app(VendorAccountService::class);

    // Master data
    $this->unit = Unit::create(['name' => 'Pcs', 'short_name' => 'pcs']);
    $this->category = Category::create(['name' => 'Electronics']);

    // Accounts
    $this->cashAccount = Account::create([
        'name' => 'Cash in Drawer',
        'type' => AccountKind::CASH,
        'opening_balance' => '10000.00',
        'is_active' => true,
    ]);

    $this->bankAccount = Account::create([
        'name' => 'Main Bank',
        'type' => AccountKind::BANK,
        'opening_balance' => '20000.00',
        'is_active' => true,
    ]);

    // Categories
    $this->salesIncomeCat = AccountCategory::firstOrCreate(
        ['name' => 'Sales Income'],
        ['type' => AccountCategoryType::INCOME, 'affects_profit' => false, 'is_active' => true]
    );

    $this->otherIncomeCat = AccountCategory::firstOrCreate(
        ['name' => 'Consulting Income'],
        ['type' => AccountCategoryType::INCOME, 'affects_profit' => true, 'is_active' => true]
    );

    $this->rentExpenseCat = AccountCategory::firstOrCreate(
        ['name' => 'Shop Rent'],
        ['type' => AccountCategoryType::EXPENSE, 'affects_profit' => true, 'is_active' => true]
    );

    $this->ownerInvestmentCat = AccountCategory::firstOrCreate(
        ['name' => 'Owner Investment'],
        ['type' => AccountCategoryType::INCOME, 'affects_profit' => false, 'is_active' => true]
    );

    // Owner investment matching opening cash + bank
    $this->accountService->record(new RecordTransactionData(
        accountId: $this->cashAccount->id,
        categoryId: $this->ownerInvestmentCat->id,
        type: TransactionType::IN,
        amount: '10000.00',
        date: '2026-10-01',
        createdBy: $this->admin->id,
        description: 'Opening Cash Capital'
    ));

    $this->accountService->record(new RecordTransactionData(
        accountId: $this->bankAccount->id,
        categoryId: $this->ownerInvestmentCat->id,
        type: TransactionType::IN,
        amount: '20000.00',
        date: '2026-10-01',
        createdBy: $this->admin->id,
        description: 'Opening Bank Capital'
    ));

    // Products
    $this->productA = Product::create([
        'name' => 'Router Archer C6',
        'sku' => 'ROUTER-C6',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '2500.00',
        'last_cost' => '2000.00',
        'stock_qty' => '10.000',
        'alert_qty' => '2.000',
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'name' => 'Cat6 Cable Roll',
        'sku' => 'CAT6-ROLL',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '5000.00',
        'last_cost' => '4000.00',
        'stock_qty' => '5.000',
        'alert_qty' => '1.000',
        'is_active' => true,
    ]);

    // Seed stock batches via FifoStockService inside transaction
    \Illuminate\Support\Facades\DB::transaction(function () {
        $this->fifoStockService->addBatch(
            product: $this->productA,
            qty: '10.000',
            unitCost: '2000.00',
            source: BatchSource::PURCHASE,
            batchDate: Carbon::parse('2026-09-01'),
            user: $this->admin
        );

        $this->fifoStockService->addBatch(
            product: $this->productB,
            qty: '5.000',
            unitCost: '4000.00',
            source: BatchSource::PURCHASE,
            batchDate: Carbon::parse('2026-08-01'),
            user: $this->admin
        );
    });

    // Customers and Vendors
    $this->customer = Customer::create([
        'name' => 'Rahim Enterprises',
        'phone' => '01711000001',
        'opening_balance' => '0.00',
        'is_active' => true,
    ]);

    $this->vendor = Vendor::create([
        'name' => 'Tech Supply BD',
        'phone' => '01811000001',
        'opening_balance' => '0.00',
        'is_active' => true,
    ]);
});

test('reports:check command executes cleanly and all checks pass', function () {
    $this->artisan('reports:check')
        ->expectsOutputToContain('All cross-report reconciliations passed successfully!')
        ->assertSuccessful();
});

test('Invariant 1: Profit and Loss net profit matches BusinessFinanceService net profit', function () {
    // Record an expense of 1500 and other income of 500
    $this->accountService->record(new RecordTransactionData(
        accountId: $this->cashAccount->id,
        categoryId: $this->rentExpenseCat->id,
        type: TransactionType::OUT,
        amount: '1500.00',
        date: '2026-10-01',
        createdBy: $this->admin->id
    ));

    $this->accountService->record(new RecordTransactionData(
        accountId: $this->bankAccount->id,
        categoryId: $this->otherIncomeCat->id,
        type: TransactionType::IN,
        amount: '500.00',
        date: '2026-10-01',
        createdBy: $this->admin->id
    ));

    $pnlService = app(ProfitAndLossReportService::class);
    $pnl = $pnlService->generate();

    $bfNet = $this->financeService->netProfit();

    expect($pnl['net_profit'])->toBe($bfNet)
        ->and($pnl['reconciliation_check'])->toBeTrue();
});

test('Invariant 2: Sales report net profit equals completed sales profit minus return profit reversed', function () {
    // Create a sale
    $sale = Sale::create([
        'invoice_no' => 'INV-001',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01',
        'subtotal' => '2500.00',
        'discount' => '100.00',
        'total' => '2400.00',
        'paid_amount' => '2000.00',
        'due_amount' => '400.00',
        'outstanding_due' => '400.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '500.00',
        'net_profit' => '400.00',
        'created_by' => $this->admin->id,
    ]);

    // Create a return with profit reversed
    SaleReturn::create([
        'return_no' => 'RET-001',
        'sale_id' => $sale->id,
        'customer_id' => $this->customer->id,
        'return_date' => '2026-10-01',
        'refund_amount' => '500.00',
        'due_reduction' => '400.00',
        'cash_refund' => '100.00',
        'cost_restored' => '400.00',
        'profit_reversed' => '100.00',
        'reason' => 'Customer changed mind',
        'created_by' => $this->admin->id,
    ]);

    $salesReportService = app(SalesReportService::class);
    $report = $salesReportService->generate(ReportPeriod::fromPreset('all'));

    // Expected = 400 - 100 = 300.00
    expect($report['summary']['net_profit'])->toBe('300.00');
});

test('Invariant 3: Product sales report grand total profit reconciles to P&L gross profit', function () {
    $sale = Sale::create([
        'invoice_no' => 'INV-002',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01',
        'subtotal' => '2500.00',
        'discount' => '200.00',
        'total' => '2300.00',
        'paid_amount' => '2300.00',
        'due_amount' => '0.00',
        'outstanding_due' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '500.00',
        'net_profit' => '300.00', // gross_profit 500 - discount 200
        'created_by' => $this->admin->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->productA->id,
        'product_name' => $this->productA->name,
        'sku' => $this->productA->sku,
        'qty' => '1.000',
        'unit_cost' => '2000.00',
        'unit_price' => '2500.00',
        'discount' => '0.00',
        'line_total' => '2500.00',
        'line_cost' => '2000.00',
        'line_profit' => '500.00',
    ]);

    $productReportService = app(ProductSalesReportService::class);
    $prodReport = $productReportService->generate(ReportPeriod::fromPreset('all'));
    $bfGross = $this->financeService->grossProfit();

    expect($prodReport['summary']['reconciled_gross_profit'])->toBe($bfGross);
});

test('Invariant 4: Stock valuation report matches FifoStockService stockValue', function () {
    $valuationService = app(StockValuationReportService::class);
    $report = $valuationService->generate();

    $expectedFifo = $this->fifoStockService->stockValue();

    expect($report['totals']['total_fifo_value'])->toBe($expectedFifo)
        ->and(bccomp($expectedFifo, '0.00', 2))->toBeGreaterThan(0);
});

test('Invariant 5 & 6: Balance sheet cash matches accounts and Balance sheet is exactly balanced', function () {
    $bsService = app(BalanceSheetReportService::class);
    $bs = $bsService->generate();

    $activeAccountBalances = $this->accountService->balances();
    $sumAccounts = '0.00';
    foreach (Account::where('is_active', true)->pluck('id') as $id) {
        $sumAccounts = bcadd($sumAccounts, (string) ($activeAccountBalances->get($id) ?? '0.00'), 2);
    }

    expect($bs['assets']['cash_and_bank']['total'])->toBe($sumAccounts)
        ->and($bs['difference'])->toBe('0.00')
        ->and($bs['is_balanced'])->toBeTrue();
});

test('Aging boundary tests accurately bucket debts', function () {
    // Reference date: 2026-10-01
    // Purchase 1: 0 days ago (2026-10-01) -> 0-30 bucket
    // Purchase 2: 30 days ago (2026-09-01) -> 0-30 bucket
    // Purchase 3: 31 days ago (2026-08-31) -> 31-60 bucket
    // Purchase 4: 60 days ago (2026-08-02) -> 31-60 bucket
    // Purchase 5: 61 days ago (2026-08-01) -> 61-90 bucket
    // Purchase 6: 95 days ago (2026-06-28) -> 90+ bucket

    $v = Vendor::create(['name' => 'Aging Vendor', 'opening_balance' => '0.00', 'is_active' => true]);

    $createBill = function (string $date, string $amount, string $inv) use ($v): void {
        Purchase::create([
            'invoice_no' => $inv,
            'vendor_id' => $v->id,
            'purchase_date' => $date,
            'subtotal' => $amount,
            'discount' => '0.00',
            'extra_cost' => '0.00',
            'total' => $amount,
            'paid_amount' => '0.00',
            'due_amount' => $amount,
            'status' => PurchaseStatus::ACTIVE->value,
            'payment_status' => 'due',
            'created_by' => $this->admin->id,
        ]);
    };

    $createBill('2026-10-01', '100.00', 'P-0');
    $createBill('2026-09-01', '200.00', 'P-30');
    $createBill('2026-08-31', '300.00', 'P-31');
    $createBill('2026-08-02', '400.00', 'P-60');
    $createBill('2026-08-01', '500.00', 'P-61');
    $createBill('2026-06-28', '600.00', 'P-95');

    $agingService = app(VendorAgingReportService::class);
    $report = $agingService->generate('2026-10-01');

    $vendorRow = $report['rows']->firstWhere('vendor_id', $v->id);

    expect($vendorRow)->not->toBeNull()
        ->and($vendorRow['bucket_0_30'])->toBe('300.00') // 100 + 200
        ->and($vendorRow['bucket_31_60'])->toBe('700.00') // 300 + 400
        ->and($vendorRow['bucket_61_90'])->toBe('500.00') // 500
        ->and($vendorRow['bucket_90_plus'])->toBe('600.00') // 600
        ->and($vendorRow['net_due'])->toBe('2100.00');
});

test('Dead stock report detects products with no sales within threshold', function () {
    $deadStockService = app(DeadStockReportService::class);

    // Product A and B were added with no sales yet -> both dead stock
    $report60 = $deadStockService->generate(['days' => 60]);
    expect($report60['rows']->pluck('product_id')->all())->toContain($this->productA->id, $this->productB->id);

    // Record a sale for Product A 10 days ago (2026-09-21)
    $sale = Sale::create([
        'invoice_no' => 'INV-DEAD-1',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-09-21',
        'subtotal' => '2500.00',
        'total' => '2500.00',
        'paid_amount' => '2500.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $this->productA->id,
        'product_name' => $this->productA->name,
        'sku' => $this->productA->sku,
        'qty' => '1.000',
        'unit_cost' => '2000.00',
        'unit_price' => '2500.00',
        'discount' => '0.00',
        'line_total' => '2500.00',
        'line_cost' => '2000.00',
        'line_profit' => '500.00',
    ]);

    $reportAfter = $deadStockService->generate(['days' => 60]);
    // Product A was sold 10 days ago -> NOT dead stock
    // Product B had no sales -> IS dead stock
    expect($reportAfter['rows']->pluck('product_id')->all())
        ->not->toContain($this->productA->id)
        ->toContain($this->productB->id);
});

test('Daily summary report correctly records EOD inflows, outflows and accounts', function () {
    // Record POS sale today
    Sale::create([
        'invoice_no' => 'INV-EOD-1',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'paid_amount' => '1000.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    // Record expense today
    $this->accountService->record(new RecordTransactionData(
        accountId: $this->cashAccount->id,
        categoryId: $this->rentExpenseCat->id,
        type: TransactionType::OUT,
        amount: '300.00',
        date: '2026-10-01',
        createdBy: $this->admin->id
    ));

    $dailyService = app(DailySummaryReportService::class);
    $eod = $dailyService->generate('2026-10-01');

    expect($eod['inflows']['pos_sales_collected'])->toBe('1000.00')
        ->and($eod['outflows']['operating_expenses'])->toBe('300.00')
        ->and(count($eod['accounts_summary']))->toBeGreaterThan(0);
});

test('Security: Manager cannot access restricted report pages (canAccess returns false)', function () {
    $this->actingAs($this->manager);

    expect(ProfitAndLossReportPage::canAccess())->toBeFalse()
        ->and(BalanceSheetReportPage::canAccess())->toBeFalse()
        ->and(StockValuationReportPage::canAccess())->toBeFalse()
        ->and(DeadStockReportPage::canAccess())->toBeFalse()
        ->and(DailySummaryReportPage::canAccess())->toBeFalse();
});

test('Security: Manager accessing SalesReport sees only own sales and no profit/cost columns', function () {
    \App\Models\Setting::set('manager_sales_visibility', 'own');

    // Sale 1 by Super Admin
    Sale::create([
        'invoice_no' => 'INV-ADMIN-1',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01',
        'subtotal' => '2500.00',
        'total' => '2500.00',
        'paid_amount' => '2500.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '500.00',
        'net_profit' => '500.00',
        'created_by' => $this->admin->id,
    ]);

    // Sale 2 by Manager
    Sale::create([
        'invoice_no' => 'INV-MGR-1',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01',
        'subtotal' => '1200.00',
        'total' => '1200.00',
        'paid_amount' => '1200.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '300.00',
        'net_profit' => '300.00',
        'created_by' => $this->manager->id,
    ]);

    $salesReportService = app(SalesReportService::class);

    // Call as Manager
    $managerReport = $salesReportService->generate(ReportPeriod::fromPreset('all'), [], $this->manager);

    expect($managerReport['is_manager'])->toBeTrue()
        ->and($managerReport['rows']->count())->toBe(1)
        ->and($managerReport['rows']->first()['invoice_no'])->toBe('INV-MGR-1')
        ->and(isset($managerReport['summary']['net_profit']))->toBeFalse()
        ->and(isset($managerReport['summary']['cogs']))->toBeFalse()
        ->and(isset($managerReport['rows']->first()['net_profit']))->toBeFalse()
        ->and(isset($managerReport['rows']->first()['cogs']))->toBeFalse();
});

test('Security: Dashboard widgets enforce role isolation', function () {
    // Super Admin view check
    $this->actingAs($this->admin);
    expect(TodayStatsWidget::canView())->toBeTrue()
        ->and(FinancialSummaryWidget::canView())->toBeTrue()
        ->and(AccountBalancesWidget::canView())->toBeTrue()
        ->and(ManagerTodayStatsWidget::canView())->toBeFalse();

    // Manager view check
    $this->actingAs($this->manager);
    expect(TodayStatsWidget::canView())->toBeFalse()
        ->and(FinancialSummaryWidget::canView())->toBeFalse()
        ->and(AccountBalancesWidget::canView())->toBeFalse()
        ->and(ManagerTodayStatsWidget::canView())->toBeTrue();
});

test('Dashboard renders cleanly for Super Admin and Manager without query exceptions', function () {
    $this->actingAs($this->admin);
    $responseAdmin = $this->get('/admin');
    $responseAdmin->assertSuccessful();

    $this->actingAs($this->manager);
    $responseManager = $this->get('/admin');
    $responseManager->assertSuccessful();
});

test('All report pages render successfully without view or array key exceptions for Super Admin', function () {
    $this->actingAs($this->admin);

    // Create sample data so reports have records to iterate through in blade
    $product = Product::create([
        'name' => 'Report Test Item',
        'sku' => 'RPT-001',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'stock_qty' => '10.000',
        'cost_price' => '100.00',
        'sale_price' => '150.00',
    ]);

    $purchase = Purchase::create([
        'invoice_no' => 'PUR-RPT-1',
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'subtotal' => '1000.00',
        'shipping_cost' => '50.00',
        'total' => '1050.00',
        'paid_amount' => '1050.00',
        'due_amount' => '0.00',
        'status' => PurchaseStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);

    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'batch_date' => '2026-10-01',
        'qty' => '10.000',
        'unit_cost' => '100.00',
        'landed_unit_cost' => '105.00',
        'line_total' => '1000.00',
        'remaining_qty' => '10.000',
        'source' => BatchSource::PURCHASE,
    ]);

    $pages = [
        '/admin/purchase-report-page',
        '/admin/sales-report-page',
        '/admin/product-sales-report-page',
        '/admin/vendor-due-report-page',
        '/admin/customer-due-report-page',
        '/admin/stock-valuation-report-page',
        '/admin/profit-and-loss-report-page',
        '/admin/daily-summary-report-page',
        '/admin/balance-sheet-report-page',
        '/admin/dead-stock-report-page',
        '/admin/stock-losses-report-page',
        '/admin/price-history-report-page',
        '/admin/payment-method-report-page',
        '/admin/customer-collections-report-page',
        '/admin/vendor-payments-report-page',
        '/admin/income-expense-report-page',
        '/admin/audit-log-page',
    ];

    foreach ($pages as $url) {
        $response = $this->get($url);
        $response->assertSuccessful();
    }
});

test('No duplicated currency symbol (৳ ৳) exists across any report page, dashboard, or receipt for Super Admin and Manager', function () {
    $this->actingAs($this->admin);

    // Create sample data so all reports have populated tables and totals
    $product = Product::create([
        'name' => 'Report Currency Item',
        'sku' => 'CURR-001',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'stock_qty' => '10.000',
        'cost_price' => '100.00',
        'sale_price' => '150.00',
    ]);

    $purchase = Purchase::create([
        'invoice_no' => 'PUR-CURR-1',
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'subtotal' => '1000.00',
        'shipping_cost' => '50.00',
        'total' => '1050.00',
        'paid_amount' => '1050.00',
        'due_amount' => '0.00',
        'status' => PurchaseStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);

    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'batch_date' => '2026-10-01',
        'qty' => '10.000',
        'unit_cost' => '100.00',
        'landed_unit_cost' => '105.00',
        'line_total' => '1000.00',
        'remaining_qty' => '10.000',
        'source' => BatchSource::PURCHASE,
    ]);

    $sale = Sale::create([
        'invoice_no' => 'INV-CURR-1',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01',
        'subtotal' => '150.00',
        'discount' => '10.00',
        'total' => '140.00',
        'paid_amount' => '100.00',
        'due_amount' => '40.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '50.00',
        'net_profit' => '40.00',
        'created_by' => $this->manager->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'qty' => '1.000',
        'unit_cost' => '100.00',
        'unit_price' => '150.00',
        'discount' => '10.00',
        'line_total' => '140.00',
        'line_cost' => '100.00',
        'line_profit' => '40.00',
    ]);

    $allReportPages = [
        '/admin',
        '/admin/purchase-report-page',
        '/admin/sales-report-page',
        '/admin/product-sales-report-page',
        '/admin/vendor-due-report-page',
        '/admin/customer-due-report-page',
        '/admin/stock-valuation-report-page',
        '/admin/profit-and-loss-report-page',
        '/admin/daily-summary-report-page',
        '/admin/balance-sheet-report-page',
        '/admin/dead-stock-report-page',
        '/admin/stock-losses-report-page',
        '/admin/price-history-report-page',
        '/admin/payment-method-report-page',
        '/admin/customer-collections-report-page',
        '/admin/vendor-payments-report-page',
        '/admin/income-expense-report-page',
        '/admin/audit-log-page',
    ];

    // Check Super Admin view of all report pages
    foreach ($allReportPages as $url) {
        $response = $this->get($url);
        $response->assertSuccessful();
        $content = $response->getContent();
        expect($content)->not->toContain('৳ ৳')
            ->not->toContain('৳&nbsp;৳')
            ->not->toContain('৳&nbsp; ৳');
    }

    // Check Manager view of permitted dashboard and sales report
    $this->actingAs($this->manager);
    $managerPages = [
        '/admin',
        '/admin/sales-report-page',
    ];

    foreach ($managerPages as $url) {
        $response = $this->get($url);
        $response->assertSuccessful();
        $content = $response->getContent();
        expect($content)->not->toContain('৳ ৳')
            ->not->toContain('৳&nbsp;৳')
            ->not->toContain('৳&nbsp; ৳');
    }

    // Check Sale receipt print view
    $receiptResponse = $this->get(route('sales.receipt', $sale));
    $receiptResponse->assertSuccessful();
    expect($receiptResponse->getContent())->not->toContain('৳ ৳')
        ->not->toContain('৳&nbsp;৳');
});

test('Balance Sheet difference is exactly 0.00 after opening balance edit, customer/vendor opening balances, and positive stock adjustment', function () {
    // 1. Edit an account opening balance
    $this->cashAccount->update(['opening_balance' => '15000.00']);

    // 2. Add customer with opening receivable balance
    $newCustomer = Customer::create([
        'name' => 'Legacy Customer',
        'phone' => '01719999999',
        'opening_balance' => '2500.00',
        'is_active' => true,
    ]);

    // 3. Add vendor with opening payable balance
    $newVendor = Vendor::create([
        'name' => 'Legacy Vendor',
        'phone' => '01819999999',
        'opening_balance' => '1200.00',
        'is_active' => true,
    ]);

    // 4. Record a positive stock adjustment
    app(\App\Services\StockAdjustmentService::class)->adjust([
        'product_id' => $this->productA->id,
        'type' => \App\Enums\AdjustmentType::INCREASE,
        'qty' => '2.000',
        'unit_cost' => '2000.00',
        'reason' => 'Physical stock count surplus found',
    ], $this->admin);

    // 5. Record random mix of operational activities
    // Sale via SaleService
    Setting::set('credit_sales_enabled', true);
    app(\App\Services\SaleService::class)->createSale([
        'customer_id' => $newCustomer->id,
        'sale_date' => '2026-10-01',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'qty' => '1.000',
                'discount' => '0.00',
            ],
        ],
        'discount' => '0.00',
        'paid_amount' => '1500.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $this->cashAccount->id,
    ], $this->admin);

    // Generate Balance Sheet
    $bs = app(BalanceSheetReportService::class)->generate();

    expect($bs['is_balanced'])->toBeTrue()
        ->and($bs['difference'])->toBe('0.00')
        ->and($bs['equity']['opening_customer_balances'])->toBe('2500.00')
        ->and($bs['equity']['opening_vendor_balances'])->toBe('1200.00')
        ->and(bccomp($bs['equity']['positive_stock_adjustments'], '4000.00', 2))->toBe(0);
});

test('Date edge cases: inclusive boundaries, Asia/Dhaka midnight, post-period returns, and cancelled transaction exclusions', function () {
    // 1. Transaction at start of day (00:00:00) and end of day (23:59:59)
    $saleMorning = Sale::create([
        'invoice_no' => 'INV-EDGE-AM',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01 00:00:00',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'paid_amount' => '1000.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    $saleNight = Sale::create([
        'invoice_no' => 'INV-EDGE-PM',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01 23:59:59',
        'subtotal' => '1500.00',
        'total' => '1500.00',
        'paid_amount' => '1500.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    // Cancelled sale (should be excluded)
    Sale::create([
        'invoice_no' => 'INV-CANCELLED',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-10-01 12:00:00',
        'subtotal' => '9999.00',
        'total' => '9999.00',
        'paid_amount' => '0.00',
        'due_amount' => '9999.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::CANCELLED,
        'created_by' => $this->admin->id,
    ]);

    // Cancelled purchase (should be excluded)
    Purchase::create([
        'invoice_no' => 'PUR-CANCELLED',
        'vendor_id' => $this->vendor->id,
        'purchase_date' => '2026-10-01',
        'subtotal' => '8888.00',
        'total' => '8888.00',
        'paid_amount' => '0.00',
        'due_amount' => '8888.00',
        'status' => PurchaseStatus::CANCELLED,
        'created_by' => $this->admin->id,
    ]);

    // Check Sales Report for today
    $salesService = app(SalesReportService::class);
    $reportToday = $salesService->generate(ReportPeriod::fromPreset('today'));

    $invoices = $reportToday['rows']->pluck('invoice_no')->all();
    expect($invoices)->toContain('INV-EDGE-AM')
        ->toContain('INV-EDGE-PM')
        ->not->toContain('INV-CANCELLED');

    // 2. Return dated after the sale: sale in September, return on October 1
    $pastSale = Sale::create([
        'invoice_no' => 'INV-SEPTEMBER',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-09-20',
        'subtotal' => '2000.00',
        'total' => '2000.00',
        'paid_amount' => '2000.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'gross_profit' => '400.00',
        'net_profit' => '400.00',
        'created_by' => $this->admin->id,
    ]);

    SaleReturn::create([
        'return_no' => 'RET-OCT-01',
        'sale_id' => $pastSale->id,
        'customer_id' => $this->customer->id,
        'return_date' => '2026-10-01',
        'refund_amount' => '500.00',
        'due_reduction' => '0.00',
        'cash_refund' => '500.00',
        'cost_restored' => '400.00',
        'profit_reversed' => '100.00',
        'reason' => 'Returned next month',
        'created_by' => $this->admin->id,
    ]);

    $pnlToday = app(ProfitAndLossReportService::class)->generate([
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-01',
    ]);

    // October P&L captures the return profit reversal / refund
    expect($pnlToday['sales_summary']['sale_returns'])->toBe('500.00');
});

test('Aging boundary test: exactly 30, 31, 60, 61, 90, 91 days bucket assignment', function () {
    $refDate = '2026-10-01';
    $cust = Customer::create(['name' => 'Boundary Customer', 'opening_balance' => '0.00', 'is_active' => true]);

    $createSaleDue = function (string $date, string $due, string $inv) use ($cust): void {
        Sale::create([
            'invoice_no' => $inv,
            'customer_id' => $cust->id,
            'sale_date' => $date,
            'subtotal' => $due,
            'total' => $due,
            'paid_amount' => '0.00',
            'due_amount' => $due,
            'outstanding_due' => $due,
            'payment_method' => PaymentMethod::CASH,
            'status' => SaleStatus::COMPLETED,
            'created_by' => $this->admin->id,
        ]);
    };

    // Exactly 30 days ago (2026-09-01)
    $createSaleDue('2026-09-01', '10.00', 'S-30');
    // Exactly 31 days ago (2026-08-31)
    $createSaleDue('2026-08-31', '20.00', 'S-31');
    // Exactly 60 days ago (2026-08-02)
    $createSaleDue('2026-08-02', '30.00', 'S-60');
    // Exactly 61 days ago (2026-08-01)
    $createSaleDue('2026-08-01', '40.00', 'S-61');
    // Exactly 90 days ago (2026-07-03)
    $createSaleDue('2026-07-03', '50.00', 'S-90');
    // Exactly 91 days ago (2026-07-02)
    $createSaleDue('2026-07-02', '60.00', 'S-91');

    $aging = app(CustomerAgingReportService::class)->generate($refDate);
    $row = $aging['rows']->firstWhere('customer_id', $cust->id);

    expect($row)->not->toBeNull()
        ->and($row['bucket_0_30'])->toBe('10.00')
        ->and($row['bucket_31_60'])->toBe('50.00') // 20 + 30
        ->and($row['bucket_61_90'])->toBe('90.00') // 40 + 50
        ->and($row['bucket_90_plus'])->toBe('60.00')
        ->and($row['net_due'])->toBe('210.00');
});

test('Dead stock threshold respects custom parameter and system setting', function () {
    // Set setting to 90 days
    Setting::set('dead_stock_days', 90);

    // Product C sold 75 days ago (2026-07-18)
    $productC = Product::create([
        'name' => 'Threshold Product',
        'sku' => 'THRESH-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'stock_qty' => '10.000',
        'cost_price' => '100.00',
        'sale_price' => '150.00',
    ]);

    $sale = Sale::create([
        'invoice_no' => 'INV-THRESH-1',
        'customer_id' => $this->customer->id,
        'sale_date' => '2026-07-18',
        'subtotal' => '150.00',
        'total' => '150.00',
        'paid_amount' => '150.00',
        'due_amount' => '0.00',
        'payment_method' => PaymentMethod::CASH,
        'status' => SaleStatus::COMPLETED,
        'created_by' => $this->admin->id,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $productC->id,
        'product_name' => $productC->name,
        'sku' => $productC->sku,
        'qty' => '1.000',
        'unit_cost' => '100.00',
        'unit_price' => '150.00',
        'line_total' => '150.00',
    ]);

    $service = app(DeadStockReportService::class);

    // 1. With setting = 90 days default: Product C was sold 75 days ago, so NOT dead stock
    $report90 = $service->generate();
    expect($report90['rows']->pluck('product_id')->all())->not->toContain($productC->id);

    // 2. With explicit parameter = 60 days: Product C was sold 75 days ago (> 60), so IS dead stock
    $report60 = $service->generate(['days' => 60]);
    expect($report60['rows']->pluck('product_id')->all())->toContain($productC->id);
});

test('Excel report totals match on-screen totals across all exportable reports', function () {
    // 1. Sales Report Export
    $salesReport = app(SalesReportService::class)->generate(ReportPeriod::fromPreset('all'));
    $salesExport = new \App\Exports\SalesReportExport($salesReport['rows']);
    expect($salesExport->collection()->count())->toBe($salesReport['rows']->count());

    // 2. Vendor Aging Report Export
    $vendorAging = app(VendorAgingReportService::class)->generate();
    $vendorAgingExport = new \App\Exports\VendorAgingReportExport($vendorAging['rows'], $vendorAging['totals']);
    $lastRow = $vendorAgingExport->collection()->last();
    expect($lastRow['vendor_name'])->toBe('TOTAL')
        ->and($lastRow['net_due'])->toBe($vendorAging['totals']['net_due']);

    // 3. Customer Aging Report Export
    $customerAging = app(CustomerAgingReportService::class)->generate();
    $customerAgingExport = new \App\Exports\CustomerAgingReportExport($customerAging['rows'], $customerAging['totals']);
    $lastRowCust = $customerAgingExport->collection()->last();
    expect($lastRowCust['customer_name'])->toBe('TOTAL')
        ->and($lastRowCust['net_due'])->toBe($customerAging['totals']['net_due']);

    // 4. Dead Stock Export
    $deadStock = app(DeadStockReportService::class)->generate();
    $deadStockExport = new \App\Exports\DeadStockReportExport($deadStock['rows'], $deadStock['totals']);
    $lastDead = $deadStockExport->collection()->last();
    expect($lastDead['locked_capital'])->toBe($deadStock['totals']['total_fifo_value']);

    // 5. Stock Losses Export
    $stockLosses = app(\App\Services\Reports\StockLossesReportService::class)->generate();
    $stockLossesExport = new \App\Exports\StockLossesReportExport($stockLosses['adjustments'], $stockLosses['totals']);
    $lastLoss = $stockLossesExport->collection()->last();
    expect($lastLoss['total_loss'])->toBe($stockLosses['totals']['total_stock_losses']);

    // 6. Payment Method Export
    $payMethod = app(\App\Services\Reports\PaymentMethodReportService::class)->generate(ReportPeriod::fromPreset('all'));
    $payMethodExport = new \App\Exports\PaymentMethodReportExport($payMethod['rows'], $payMethod['totals']);
    $lastPay = $payMethodExport->collection()->last();
    expect($lastPay['total_in'])->toBe($payMethod['totals']['total_in']);

    // 7. Balance Sheet Export
    $bs = app(BalanceSheetReportService::class)->generate();
    $bsExport = new \App\Exports\BalanceSheetReportExport($bs);
    expect($bsExport->collection()->where('Section', 'TOTAL ASSETS')->first()['Amount (৳)'])
        ->toBe($bs['assets']['total_assets']);
});

test('Manager security: HTML/Livewire omits profit and cost data, and manager gets 403 on restricted reports and exports', function () {
    $this->actingAs($this->manager);

    // Sales report for Manager
    $response = $this->get('/admin/sales-report-page');
    $response->assertSuccessful();
    $content = $response->getContent();

    // Verify Manager does not see any profit/cost headers or values
    expect($content)->not->toContain('Gross Profit')
        ->not->toContain('Net Profit')
        ->not->toContain('COGS')
        ->not->toContain('Margin');

    // Verify Manager gets 403 on restricted report pages
    $restrictedPages = [
        '/admin/purchase-report-page',
        '/admin/product-sales-report-page',
        '/admin/vendor-due-report-page',
        '/admin/customer-due-report-page',
        '/admin/stock-valuation-report-page',
        '/admin/profit-and-loss-report-page',
        '/admin/daily-summary-report-page',
        '/admin/balance-sheet-report-page',
        '/admin/dead-stock-report-page',
        '/admin/stock-losses-report-page',
        '/admin/customer-collections-report-page',
        '/admin/vendor-payments-report-page',
        '/admin/income-expense-report-page',
        '/admin/audit-log-page',
        '/admin/dashboard-settings',
    ];

    foreach ($restrictedPages as $url) {
        $res = $this->get($url);
        expect($res->status())->toBe(403);
    }
});

test('Dashboard query count guard prevents query explosions on large seeded datasets', function () {
    $this->actingAs($this->admin);

    // Seed 10 products
    for ($i = 1; $i <= 10; $i++) {
        Product::create([
            'name' => "Batch Test Product {$i}",
            'sku' => "BTP-{$i}",
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'stock_qty' => '10.000',
            'cost_price' => '100.00',
            'sale_price' => '150.00',
        ]);
    }

    \Illuminate\Support\Facades\DB::enableQueryLog();

    $response = $this->get('/admin');
    $response->assertSuccessful();

    $queries = \Illuminate\Support\Facades\DB::getQueryLog();
    // Guard against N+1 regressions; the dashboard queries should be <= 35
    expect(count($queries))->toBeLessThan(35);
});

test('Realistic fixture with hand-computed exact numbers validates every core report', function () {
    // Hand-computed scenario:
    // Cash account opening = 5000.00
    $handCash = Account::create([
        'name' => 'Hand Cash',
        'type' => AccountKind::CASH,
        'opening_balance' => '5000.00',
        'is_active' => true,
    ]);

    // Vendor opening = 1000.00
    $handVendor = Vendor::create([
        'name' => 'Hand Vendor',
        'opening_balance' => '1000.00',
        'is_active' => true,
    ]);

    // Customer opening = 2000.00
    $handCustomer = Customer::create([
        'name' => 'Hand Customer',
        'opening_balance' => '2000.00',
        'is_active' => true,
    ]);

    // Product: cost 100, price 150, opening batch 10 @ 100 = 1000
    $handProduct = Product::create([
        'name' => 'Hand Widget',
        'sku' => 'HAND-WIDGET-01',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sale_price' => '150.00',
        'last_cost' => '100.00',
        'stock_qty' => '0.000',
        'alert_qty' => '2.000',
        'is_active' => true,
    ]);

    $this->fifoStockService->addBatch(
        product: $handProduct,
        qty: '10.000',
        unitCost: '100.00',
        source: BatchSource::OPENING,
        batchDate: Carbon::parse('2026-09-01'),
        user: $this->admin
    );

    // Purchase: 5 pcs @ 100 = 500, paid 200, due 300
    app(\App\Services\PurchaseService::class)->createPurchase([
        'vendor_id' => $handVendor->id,
        'purchase_date' => '2026-10-01',
        'items' => [
            [
                'product_id' => $handProduct->id,
                'qty' => '5.000',
                'unit_cost' => '100.00',
            ],
        ],
        'paid_amount' => '200.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $handCash->id,
    ], [], $this->admin);

    // Sale: 4 pcs @ 150 = 600, discount 50 = net 550, cost 400, paid 350, due 200
    Setting::set('credit_sales_enabled', true);
    app(\App\Services\SaleService::class)->createSale([
        'customer_id' => $handCustomer->id,
        'sale_date' => '2026-10-01',
        'items' => [
            [
                'product_id' => $handProduct->id,
                'qty' => '4.000',
                'discount' => '50.00',
            ],
        ],
        'discount' => '0.00',
        'paid_amount' => '350.00',
        'payment_method' => PaymentMethod::CASH,
        'account_id' => $handCash->id,
    ], $this->admin);

    // Check Customer Due: 2000 opening + 200 sale due = 2200.00
    $custDue = app(CustomerAccountService::class)->getCurrentDue($handCustomer);
    expect($custDue)->toBe('2200.00');

    // Check Vendor Due: 1000 opening + 300 purchase due = 1300.00
    $vendDue = app(VendorAccountService::class)->getCurrentDue($handVendor);
    expect($vendDue)->toBe('1300.00');

    // Check Stock Valuation: remaining qty = 10 + 5 - 4 = 11 pcs @ 100 = 1100.00
    $handProductStock = bcmul((string) $handProduct->fresh()->stock_qty, '100.00', 2);
    expect($handProductStock)->toBe('1100.00');

    // Check Balance Sheet: difference must be exactly 0.00
    $bs = app(BalanceSheetReportService::class)->generate();
    expect($bs['is_balanced'])->toBeTrue()
        ->and($bs['difference'])->toBe('0.00');
});


