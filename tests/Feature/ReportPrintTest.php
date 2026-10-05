<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportPrintTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleName::SUPER_ADMIN->value]);
        Role::firstOrCreate(['name' => RoleName::MANAGER->value]);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@example.com',
        ]);
        $this->superAdmin->assignRole(RoleName::SUPER_ADMIN->value);

        $this->manager = User::factory()->create([
            'email' => 'manager@example.com',
        ]);
        $this->manager->assignRole(RoleName::MANAGER->value);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function printRoutesProvider(): array
    {
        return [
            'balance_sheet' => ['reports.print.balance-sheet', []],
            'sales' => ['reports.print.sales', ['period' => 'this_month']],
            'product_sales' => ['reports.print.product-sales', ['period' => 'this_month']],
            'purchases' => ['reports.print.purchases', ['period' => 'this_month']],
            'profit_and_loss' => ['reports.print.profit-and-loss', ['period' => 'this_month']],
            'daily_summary_a4' => ['reports.print.daily-summary', ['format' => 'a4']],
            'daily_summary_80mm' => ['reports.print.daily-summary', ['format' => '80mm']],
            'stock_valuation' => ['reports.print.stock-valuation', []],
            'customer_due' => ['reports.print.customer-due', []],
            'vendor_due' => ['reports.print.vendor-due', []],
            'dead_stock' => ['reports.print.dead-stock', []],
            'stock_losses' => ['reports.print.stock-losses', ['period' => 'this_month']],
            'customer_collections' => ['reports.print.customer-collections', ['period' => 'this_month']],
            'vendor_payments' => ['reports.print.vendor-payments', ['period' => 'this_month']],
            'payment_method' => ['reports.print.payment-method', ['period' => 'this_month']],
            'income_expense' => ['reports.print.income-expense', ['period' => 'this_month']],
            'price_history' => ['reports.print.price-history', []],
            'audit_log' => ['reports.print.audit-log', []],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('printRoutesProvider')]
    public function test_super_admin_can_access_all_print_routes_without_duplicated_currency(string $routeName, array $params): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route($routeName, $params));

        $response->assertOk();

        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringNotContainsString('৳ ৳', $content, "Route {$routeName} contains duplicated currency symbol '৳ ৳'");
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('printRoutesProvider')]
    public function test_manager_access_control_on_print_routes(string $routeName, array $params): void
    {
        $response = $this->actingAs($this->manager)->get(route($routeName, $params));

        if ($routeName === 'reports.print.sales') {
            // Manager is permitted to print sales report
            $response->assertOk();
            $content = $response->getContent();
            $this->assertStringNotContainsString('৳ ৳', $content);
            // With default 'all', Manager sees the Cashier column, but Profit/Margin must always be hidden
            $this->assertStringContainsString('<th>Cashier</th>', $content);
            $this->assertStringNotContainsString('<th>Profit</th>', $content);

            // With 'own' mode, Cashier column is hidden for Manager
            \App\Models\Setting::set('manager_sales_visibility', 'own');
            $responseOwn = $this->actingAs($this->manager)->get(route($routeName, $params));
            $this->assertStringNotContainsString('<th>Cashier</th>', $responseOwn->getContent());
            \App\Models\Setting::set('manager_sales_visibility', 'all');
        } else {
            // All other financial report prints must be forbidden (403)
            $response->assertForbidden();
        }
    }

    public function test_balance_sheet_print_contains_key_financial_totals_and_no_filament_layout(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('reports.print.balance-sheet'));

        $response->assertOk();
        $response->assertSee('Statement of Financial Position (Balance Sheet)', false);
        $response->assertSee('1. Assets', false);
        $response->assertSee('2. Liabilities', false);
        $response->assertSee("3. Owner's Equity", false);
        $response->assertSee('TOTAL ASSETS', false);
        $response->assertSee('TOTAL LIABILITIES', false);
        $response->assertSee('TOTAL EQUITY', false);
        $response->assertSee('TOTAL LIABILITIES & EQUITY', false);
        $response->assertDontSee('fi-layout');
        $response->assertDontSee('fi-sidebar');
        $response->assertDontSee('wire:id');
        $this->assertStringNotContainsString('৳ ৳', $response->getContent());
    }

    public function test_daily_summary_print_a4_and_80mm_renders_properly(): void
    {
        // A4 format
        $responseA4 = $this->actingAs($this->superAdmin)->get(route('reports.print.daily-summary', ['format' => 'a4']));
        $responseA4->assertOk();
        $responseA4->assertSee('Daily Financial &amp; Operations Summary', false);
        $responseA4->assertSee('1. Daily Cash Inflow & Outflow Summary', false);
        $responseA4->assertSee('2. Account Reconciliation (EOD)', false);
        $responseA4->assertSee('3. Physical Stock Movements (Qty)', false);
        $this->assertStringNotContainsString('৳ ৳', $responseA4->getContent());

        // 80mm POS format
        $response80 = $this->actingAs($this->superAdmin)->get(route('reports.print.daily-summary', ['format' => '80mm']));
        $response80->assertOk();
        $response80->assertSee('Daily EOD Summary', false);
        $response80->assertSee('CASH INFLOWS:', false);
        $response80->assertSee('CASH OUTFLOWS:', false);
        $response80->assertSee('NET CASH FLOW:', false);
        $response80->assertSee('CLOSING ACCOUNTS (EOD):', false);
        $this->assertStringNotContainsString('৳ ৳', $response80->getContent());
    }
}
