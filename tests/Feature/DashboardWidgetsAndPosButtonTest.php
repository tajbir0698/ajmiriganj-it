<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DashboardSettings;
use App\Filament\Widgets\AccountBalancesWidget;
use App\Filament\Widgets\FinancialSummaryWidget;
use App\Filament\Widgets\LatestSalesWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\ManagerTodayStatsWidget;
use App\Filament\Widgets\MonthComparisonWidget;
use App\Filament\Widgets\PendingRestockRequestsWidget;
use App\Filament\Widgets\SalesTrendChartWidget;
use App\Filament\Widgets\TodayStatsWidget;
use App\Filament\Widgets\TopProductsByQuantityWidget;
use App\Filament\Widgets\TopProductsWidget;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardWidgetRegistry;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value]);
    Role::firstOrCreate(['name' => RoleName::MANAGER->value]);

    $this->admin = User::factory()->create([
        'name' => 'Super Admin User',
        'email' => 'admin@example.com',
    ]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->manager = User::factory()->create([
        'name' => 'Store Manager User',
        'email' => 'manager@example.com',
    ]);
    $this->manager->assignRole(RoleName::MANAGER->value);

    $this->category = Category::create(['name' => 'General', 'slug' => 'general']);
    $this->unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);

    DashboardWidgetRegistry::clearRuntimeCache();
});

test('architecture test: every widget class in app/Filament/Widgets is registered in DashboardWidgetRegistry', function () {
    $widgetFiles = File::files(app_path('Filament/Widgets'));
    $registered = DashboardWidgetRegistry::getRegisteredWidgets();
    $registeredClasses = array_column($registered, 'class');

    expect($widgetFiles)->not->toBeEmpty();

    foreach ($widgetFiles as $file) {
        $className = 'App\\Filament\\Widgets\\' . $file->getFilenameWithoutExtension();
        if (class_exists($className) && is_subclass_of($className, Widget::class)) {
            expect($registeredClasses)->toContain($className);
        }
    }
});

test('default-deny: unregistered widget classes are hidden from both Manager and Super Admin', function () {
    $unregisteredClass = 'App\\Filament\\Widgets\\UnregisteredFutureWidget';

    expect(DashboardWidgetRegistry::isWidgetVisibleForUser($unregisteredClass, $this->admin))->toBeFalse();
    expect(DashboardWidgetRegistry::isWidgetVisibleForUser($unregisteredClass, $this->manager))->toBeFalse();
    expect(DashboardWidgetRegistry::isWidgetVisibleForUser($unregisteredClass, null))->toBeFalse();
});

test('widget order and layout strictly match existing dashboard order', function () {
    $registered = DashboardWidgetRegistry::getRegisteredWidgets();
    $registeredKeys = array_keys($registered);

    $expectedOrder = [
        'today_stats',
        'account_balances',
        'month_comparison',
        'low_stock',
        'financial_summary',
        'sales_trend_chart',
        'top_products_revenue',
        'top_products_quantity',
        'latest_sales',
        'manager_today_stats',
        'restock_requests_status',
    ];

    expect($registeredKeys)->toBe($expectedOrder);

    // Super Admin default widgets order
    $adminWidgets = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->admin);
    expect($adminWidgets)->toBe([
        TodayStatsWidget::class,
        AccountBalancesWidget::class,
        MonthComparisonWidget::class,
        LowStockWidget::class,
        FinancialSummaryWidget::class,
        SalesTrendChartWidget::class,
        TopProductsWidget::class,
        TopProductsByQuantityWidget::class,
        LatestSalesWidget::class,
        PendingRestockRequestsWidget::class,
    ]);

    // Manager default widgets order (when manager_can_request_restock is false)
    Setting::set('manager_can_request_restock', false);
    DashboardWidgetRegistry::clearRuntimeCache();

    $managerWidgets = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->manager);
    expect($managerWidgets)->toBe([
        LowStockWidget::class,
        ManagerTodayStatsWidget::class,
    ]);

    // Manager default widgets order (when manager_can_request_restock is true)
    Setting::set('manager_can_request_restock', true);
    DashboardWidgetRegistry::clearRuntimeCache();

    $managerWidgetsWithRestock = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->manager);
    expect($managerWidgetsWithRestock)->toBe([
        LowStockWidget::class,
        ManagerTodayStatsWidget::class,
        PendingRestockRequestsWidget::class,
    ]);
});

test('POS button is prominently visible at top of dashboard for both Super Admin and Manager', function () {
    // Super Admin dashboard
    $resAdmin = $this->actingAs($this->admin)->get('/admin');
    $resAdmin->assertSuccessful();
    $resAdmin->assertSee('Open POS / New Sale');
    $resAdmin->assertSee('id="dashboard-open-pos-button"', false);
    $resAdmin->assertSee('/pos', false);

    // Manager dashboard
    $resManager = $this->actingAs($this->manager)->get('/admin');
    $resManager->assertSuccessful();
    $resManager->assertSee('Open POS / New Sale');
    $resManager->assertSee('id="dashboard-open-pos-button"', false);
    $resManager->assertSee('/pos', false);
});

test('Dashboard Settings page access control: Super Admin allowed, Manager gets 403', function () {
    // Super Admin accesses Dashboard Settings
    $resAdmin = $this->actingAs($this->admin)->get('/admin/dashboard-settings');
    $resAdmin->assertSuccessful();
    $resAdmin->assertSee('Dashboard Settings');
    $resAdmin->assertSee('Role-Based Dashboard Visibility');

    // Manager gets 403
    $resManager = $this->actingAs($this->manager)->get('/admin/dashboard-settings');
    $resManager->assertStatus(403);
});

test('Manager direct Livewire action calls to DashboardSettings are refused with 403', function () {
    // Direct mount & action call attempt as Manager
    Livewire::actingAs($this->manager)
        ->test(DashboardSettings::class)
        ->assertStatus(403);
});

test('Super Admin cannot enable admin-only widget for Manager; tampered payload is rejected', function () {
    $component = Livewire::actingAs($this->admin)->test(DashboardSettings::class);

    // Attempt to tamper with payload by setting manager.financial_summary = true
    $component->set('manager.financial_summary', true);
    $component->call('save');
    $component->assertHasErrors(['manager.financial_summary']);

    // Direct registry call also throws ValidationException
    expect(function () {
        DashboardWidgetRegistry::saveSettings([
            'manager' => ['financial_summary' => true],
        ], $this->admin);
    })->toThrow(ValidationException::class);

    // Verify setting was NOT persisted
    $settings = DashboardWidgetRegistry::getSettings();
    expect($settings['manager']['financial_summary'])->toBeFalse();
});

test('DashboardWidgetRegistry::saveSettings ignores unknown keys and strips them', function () {
    $cleanSettings = [
        'super_admin' => [
            'today_stats' => true,
            'malicious_key_admin' => true,
        ],
        'manager' => [
            'low_stock' => true,
            'malicious_key_manager' => true,
        ],
    ];

    DashboardWidgetRegistry::saveSettings($cleanSettings, $this->admin);

    $saved = DashboardWidgetRegistry::getSettings();
    expect(isset($saved['super_admin']['malicious_key_admin']))->toBeFalse();
    expect(isset($saved['manager']['malicious_key_manager']))->toBeFalse();
    expect($saved['super_admin']['today_stats'])->toBeTrue();
    expect($saved['manager']['low_stock'])->toBeTrue();
});

test('server-side code allow-list wins over direct DB tampering of settings', function () {
    // Manually write JSON with admin-only widgets enabled for Manager
    Setting::set(DashboardWidgetRegistry::SETTING_KEY, json_encode([
        'super_admin' => ['today_stats' => true],
        'manager' => [
            'today_stats' => true,
            'financial_summary' => true,
            'account_balances' => true,
            'low_stock' => true,
        ],
    ]));
    DashboardWidgetRegistry::clearRuntimeCache();

    // Verify isWidgetVisibleForUser strictly blocks admin-only widgets for Manager
    expect(DashboardWidgetRegistry::isWidgetVisibleForUser(TodayStatsWidget::class, $this->manager))->toBeFalse();
    expect(DashboardWidgetRegistry::isWidgetVisibleForUser(FinancialSummaryWidget::class, $this->manager))->toBeFalse();
    expect(DashboardWidgetRegistry::isWidgetVisibleForUser(AccountBalancesWidget::class, $this->manager))->toBeFalse();
    expect(DashboardWidgetRegistry::isWidgetVisibleForUser(LowStockWidget::class, $this->manager))->toBeTrue();

    // Verify getEnabledWidgetsForUser does not include any admin-only widgets
    $managerWidgets = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->manager);
    expect($managerWidgets)->not->toContain(TodayStatsWidget::class);
    expect($managerWidgets)->not->toContain(FinancialSummaryWidget::class);
    expect($managerWidgets)->not->toContain(AccountBalancesWidget::class);
    expect($managerWidgets)->toContain(LowStockWidget::class);
});

test('Super Admin can toggle widgets on and off', function () {
    Livewire::actingAs($this->admin)->test(DashboardSettings::class)
        ->set('super_admin.today_stats', false)
        ->set('manager.low_stock', false)
        ->call('save');

    $adminWidgets = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->admin);
    expect($adminWidgets)->not->toContain(TodayStatsWidget::class);

    $managerWidgets = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->manager);
    expect($managerWidgets)->not->toContain(LowStockWidget::class);

    // Reset to defaults
    Livewire::actingAs($this->admin)->test(DashboardSettings::class)
        ->call('resetDefaults');

    $resetAdminWidgets = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->admin);
    expect($resetAdminWidgets)->toContain(TodayStatsWidget::class);

    $resetManagerWidgets = DashboardWidgetRegistry::getEnabledWidgetsForUser($this->manager);
    expect($resetManagerWidgets)->toContain(LowStockWidget::class);
});

test('all-off state: shows POS button and empty state message with settings link only for Super Admin', function () {
    // Turn all widgets off
    $registered = DashboardWidgetRegistry::getRegisteredWidgets();
    $allOff = [
        'super_admin' => array_fill_keys(array_keys($registered), false),
        'manager' => array_fill_keys(array_keys($registered), false),
    ];
    DashboardWidgetRegistry::saveSettings($allOff, $this->admin);

    // Super Admin view:
    $resAdmin = $this->actingAs($this->admin)->get('/admin');
    $resAdmin->assertSuccessful();
    $resAdmin->assertSee('Open POS / New Sale');
    $resAdmin->assertSee('All dashboard cards are turned off');
    $resAdmin->assertSee('/admin/dashboard-settings');

    // Manager view:
    $resManager = $this->actingAs($this->manager)->get('/admin');
    $resManager->assertSuccessful();
    $resManager->assertSee('Open POS / New Sale');
    $resManager->assertSee('All dashboard cards are turned off');
    // Manager must NOT see the link to /admin/dashboard-settings
    $resManager->assertDontSee('/admin/dashboard-settings');
});

test('Manager dashboard HTML never leaks cost, profit, margins, or account balances', function () {
    // Create product with sensitive cost
    Product::create([
        'name' => 'Secret Cost Widget Test Product',
        'sku' => 'SCWTP-001',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'stock_qty' => '2.000',
        'alert_qty' => '5.000',
        'cost_price' => '888.77',
        'sale_price' => '1200.00',
    ]);

    $resManager = $this->actingAs($this->manager)->get('/admin');
    $resManager->assertSuccessful();

    // Cost price and internal sensitive keywords must never be rendered to Manager
    $resManager->assertDontSee('888.77');
    $resManager->assertDontSee('Gross Profit');
    $resManager->assertDontSee('Month-over-Month Comparison');
    $resManager->assertDontSee('Financial Overview');
    $resManager->assertDontSee('Account Balances');
});

test('Manager dashboard HTML and Livewire snapshot contain no link to Dashboard Settings and no admin-only widget across all states', function () {
    $adminOnlyWidgets = [
        'TodayStatsWidget',
        'AccountBalancesWidget',
        'MonthComparisonWidget',
        'FinancialSummaryWidget',
        'SalesTrendChartWidget',
        'TopProductsWidget',
        'TopProductsByQuantityWidget',
        'LatestSalesWidget',
    ];

    $sensitivePhrases = [
        'Gross Profit',
        'Account Balances',
        'Month-over-Month Comparison',
        'Financial Overview',
        'Recent Sales Invoices',
        'Top Products by Revenue & Profit',
        'Top Fast-Moving Products',
    ];

    $states = [
        'defaults' => function () {
            DashboardWidgetRegistry::resetToDefaults($this->admin);
        },
        'manager_today_stats_off' => function () {
            $settings = DashboardWidgetRegistry::getDefaults();
            $settings['manager']['manager_today_stats'] = false;
            DashboardWidgetRegistry::saveSettings($settings, $this->admin);
        },
        'low_stock_off' => function () {
            $settings = DashboardWidgetRegistry::getDefaults();
            $settings['manager']['low_stock'] = false;
            DashboardWidgetRegistry::saveSettings($settings, $this->admin);
        },
        'restock_requests_status_off' => function () {
            $settings = DashboardWidgetRegistry::getDefaults();
            $settings['manager']['restock_requests_status'] = false;
            DashboardWidgetRegistry::saveSettings($settings, $this->admin);
            Setting::set('manager_can_request_restock', false);
        },
        'all_off' => function () {
            $registered = DashboardWidgetRegistry::getRegisteredWidgets();
            $allOff = [
                'super_admin' => array_fill_keys(array_keys($registered), false),
                'manager' => array_fill_keys(array_keys($registered), false),
            ];
            DashboardWidgetRegistry::saveSettings($allOff, $this->admin);
        },
        'tampered_stored_settings' => function () {
            Setting::set(DashboardWidgetRegistry::SETTING_KEY, json_encode([
                'super_admin' => ['today_stats' => true],
                'manager' => [
                    'today_stats' => true,
                    'account_balances' => true,
                    'month_comparison' => true,
                    'financial_summary' => true,
                    'sales_trend_chart' => true,
                    'top_products_revenue' => true,
                    'top_products_quantity' => true,
                    'latest_sales' => true,
                    'manager_today_stats' => true,
                    'low_stock' => true,
                    'restock_requests_status' => true,
                ],
            ]));
            DashboardWidgetRegistry::clearRuntimeCache();
        },
    ];

    foreach ($states as $stateName => $setup) {
        $setup();
        DashboardWidgetRegistry::clearRuntimeCache();

        $response = $this->actingAs($this->manager)->get('/admin');
        $response->assertSuccessful();
        $content = $response->getContent();

        // 1. Assert HTML contains NO link to Dashboard Settings
        expect($content)->not->toContain('/admin/dashboard-settings', "State [{$stateName}] leaked dashboard settings link in Manager HTML");
        expect($content)->not->toContain('dashboard-settings', "State [{$stateName}] leaked dashboard-settings slug in Manager HTML");

        // 2. Assert HTML contains NO admin-only widget class names or sensitive headers
        foreach ($adminOnlyWidgets as $widgetName) {
            expect($content)->not->toContain($widgetName, "State [{$stateName}] leaked widget [{$widgetName}] in Manager HTML");
        }

        foreach ($sensitivePhrases as $phrase) {
            expect($content)->not->toContain($phrase, "State [{$stateName}] leaked phrase [{$phrase}] in Manager HTML");
        }

        // 3. Assert Livewire snapshot attributes contain NO references to Dashboard Settings or admin-only widgets
        preg_match_all('/wire:snapshot="([^"]+)"/', $content, $matches);
        if (! empty($matches[1])) {
            foreach ($matches[1] as $rawSnapshot) {
                $decodedJson = html_entity_decode($rawSnapshot, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                expect($decodedJson)->not->toContain('/admin/dashboard-settings', "State [{$stateName}] leaked dashboard settings link in Livewire snapshot");
                expect($decodedJson)->not->toContain('dashboard-settings', "State [{$stateName}] leaked dashboard-settings slug in Livewire snapshot");

                foreach ($adminOnlyWidgets as $widgetName) {
                    expect($decodedJson)->not->toContain($widgetName, "State [{$stateName}] leaked [{$widgetName}] in Livewire snapshot");
                }
            }
        }
    }
});

test('sidebar accordion script hook is rendered on admin panel', function () {
    $response = $this->actingAs($this->admin)->get('/admin');
    $response->assertSuccessful();
    $response->assertSee('initSidebarAccordion');
    $response->assertSee('toggleCollapsedGroup');
});

test('customers index page renders without bccomp ValueError on current_due column', function () {
    \App\Models\Customer::create([
        'name' => 'Zero Due Customer',
        'opening_balance' => '0.00',
        'is_active' => true,
    ]);
    \App\Models\Customer::create([
        'name' => 'Positive Due Customer',
        'opening_balance' => '150.00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/customers');
    $response->assertSuccessful();
    $response->assertSee('Zero Due Customer');
    $response->assertSee('Positive Due Customer');
    $response->assertSee('৳ 150.00');
});
