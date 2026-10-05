<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
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
use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DashboardWidgetRegistry
{
    public const SETTING_KEY = 'dashboard_widgets';

    protected static ?array $runtimeCache = null;

    public static function clearRuntimeCache(): void
    {
        static::$runtimeCache = null;
    }

    /**
     * Complete registry of all dashboard widgets.
     * Order here strictly defines the dashboard presentation order.
     */
    public static function getRegisteredWidgets(): array
    {
        return [
            'today_stats' => [
                'key' => 'today_stats',
                'class' => TodayStatsWidget::class,
                'label' => "Today's Performance",
                'description' => "Today's sales count, revenue, gross profit, and collection status.",
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'account_balances' => [
                'key' => 'account_balances',
                'class' => AccountBalancesWidget::class,
                'label' => 'Account Balances',
                'description' => 'Real-time balances across cash, bank, and mobile financial accounts.',
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'month_comparison' => [
                'key' => 'month_comparison',
                'class' => MonthComparisonWidget::class,
                'label' => 'Month-over-Month Comparison',
                'description' => 'Current month vs previous month sales, growth rate, and gross profit.',
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'low_stock' => [
                'key' => 'low_stock',
                'class' => LowStockWidget::class,
                'label' => 'Low Stock & Out of Stock Alerts',
                'description' => 'Inventory items at or below reorder threshold (no cost or vendor data).',
                'admin_only' => false,
                'default_super_admin' => true,
                'default_manager' => true,
            ],
            'financial_summary' => [
                'key' => 'financial_summary',
                'class' => FinancialSummaryWidget::class,
                'label' => 'Financial Overview',
                'description' => 'Total inventory valuation, cash/bank liquidity, customer receivables, and vendor payables.',
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'sales_trend_chart' => [
                'key' => 'sales_trend_chart',
                'class' => SalesTrendChartWidget::class,
                'label' => '30-Day Sales & Profit Trend',
                'description' => 'Daily trend chart showing gross profit and revenue trajectories.',
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'top_products_revenue' => [
                'key' => 'top_products_revenue',
                'class' => TopProductsWidget::class,
                'label' => 'Top Products by Revenue & Profit',
                'description' => 'Top performing items ranked by total revenue and profit contributions.',
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'top_products_quantity' => [
                'key' => 'top_products_quantity',
                'class' => TopProductsByQuantityWidget::class,
                'label' => 'Top Fast-Moving Products',
                'description' => 'Products with the highest quantity sold in the last 30 days.',
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'latest_sales' => [
                'key' => 'latest_sales',
                'class' => LatestSalesWidget::class,
                'label' => 'Recent Sales Invoices',
                'description' => 'Most recent sales transactions with customer names and profit margins.',
                'admin_only' => true,
                'default_super_admin' => true,
                'default_manager' => false,
            ],
            'manager_today_stats' => [
                'key' => 'manager_today_stats',
                'class' => ManagerTodayStatsWidget::class,
                'label' => "Manager Today's Sales",
                'description' => "Today's completed sales count and total collected amount (strictly safe).",
                'admin_only' => false,
                'default_super_admin' => false,
                'default_manager' => true,
            ],
            'restock_requests_status' => [
                'key' => 'restock_requests_status',
                'class' => PendingRestockRequestsWidget::class,
                'label' => 'Restock Requests Overview',
                'description' => 'Pending requests count for Super Admin, and request status tracking for Manager.',
                'admin_only' => false,
                'default_super_admin' => true,
                'default_manager' => true,
                'condition' => fn (?User $user = null) => ($user ?? auth()->user())?->isSuperAdmin() || (bool) Setting::get('manager_can_request_restock', false),
            ],
        ];
    }

    public static function isManagerSafe(string $key): bool
    {
        $widgets = self::getRegisteredWidgets();

        return isset($widgets[$key]) && ! $widgets[$key]['admin_only'];
    }

    public static function findByClass(string $class): ?array
    {
        foreach (self::getRegisteredWidgets() as $widget) {
            if ($widget['class'] === $class) {
                return $widget;
            }
        }

        return null;
    }

    public static function findByKey(string $key): ?array
    {
        return self::getRegisteredWidgets()[$key] ?? null;
    }

    public static function getDefaults(): array
    {
        $defaults = [
            'super_admin' => [],
            'manager' => [],
        ];

        foreach (self::getRegisteredWidgets() as $key => $item) {
            $defaults['super_admin'][$key] = (bool) $item['default_super_admin'];
            $defaults['manager'][$key] = (bool) $item['default_manager'];
        }

        return $defaults;
    }

    public static function getSettings(): array
    {
        if (static::$runtimeCache !== null) {
            return static::$runtimeCache;
        }

        $raw = Setting::get(self::SETTING_KEY);
        $defaults = self::getDefaults();

        if (! is_string($raw) && ! is_array($raw)) {
            return static::$runtimeCache = $defaults;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return static::$runtimeCache = $defaults;
        }

        $clean = [
            'super_admin' => [],
            'manager' => [],
        ];

        // Sanitize and ensure only known keys are preserved
        foreach (self::getRegisteredWidgets() as $key => $item) {
            $clean['super_admin'][$key] = isset($decoded['super_admin'][$key])
                ? (bool) $decoded['super_admin'][$key]
                : $defaults['super_admin'][$key];

            // Manager setting: strictly enforced by code allow-list
            if (! $item['admin_only']) {
                $clean['manager'][$key] = isset($decoded['manager'][$key])
                    ? (bool) $decoded['manager'][$key]
                    : $defaults['manager'][$key];
            } else {
                $clean['manager'][$key] = false;
            }
        }

        return static::$runtimeCache = $clean;
    }

    /**
     * Server-side validated settings save.
     */
    public static function saveSettings(array $settings, User $user): void
    {
        if (! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized. Only Super Admin can modify dashboard widget configurations.');
        }

        $registered = self::getRegisteredWidgets();
        $clean = [
            'super_admin' => [],
            'manager' => [],
        ];

        // Filter and ignore unknown keys from the payload
        $managerSettings = \Illuminate\Support\Arr::only($settings['manager'] ?? [], array_keys($registered));

        // Validate: Reject any attempt to enable an admin-only widget for Manager
        foreach ($managerSettings as $key => $enabled) {
            if ($enabled && ! self::isManagerSafe((string) $key)) {
                throw ValidationException::withMessages([
                    "manager.{$key}" => "Cannot enable admin-only widget [{$key}] for Manager role.",
                ]);
            }
        }

        // Filter and sanitize payload, ignoring unknown keys
        foreach ($registered as $key => $item) {
            $clean['super_admin'][$key] = ! empty($settings['super_admin'][$key]);

            if (! $item['admin_only']) {
                $clean['manager'][$key] = ! empty($settings['manager'][$key]);
            } else {
                $clean['manager'][$key] = false;
            }
        }

        $setting = Setting::firstOrNew(['key' => self::SETTING_KEY]);
        $setting->value = json_encode($clean);
        $setting->type = 'string';
        $setting->description = 'Active dashboard widget configuration per role';
        $setting->save();

        cache()->forget('setting.'.self::SETTING_KEY);
        self::clearRuntimeCache();
    }

    /**
     * Reset settings to hardcoded defaults.
     */
    public static function resetToDefaults(User $user): void
    {
        if (! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized. Only Super Admin can reset dashboard widget configurations.');
        }

        $setting = Setting::firstOrNew(['key' => self::SETTING_KEY]);
        $setting->value = json_encode(self::getDefaults());
        $setting->type = 'string';
        $setting->description = 'Active dashboard widget configuration per role';
        $setting->save();

        cache()->forget('setting.'.self::SETTING_KEY);
        self::clearRuntimeCache();
    }

    /**
     * Check if a widget class is visible for a user.
     * Default-deny: if widget is not registered, returns false.
     * For Manager: admin_only widgets unconditionally return false.
     */
    public static function isWidgetVisibleForUser(string $widgetClass, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $widget = self::findByClass($widgetClass);
        if (! $widget) {
            // Default-deny for unregistered widgets
            return false;
        }

        // Check custom conditional callable if present
        if (isset($widget['condition']) && is_callable($widget['condition'])) {
            if (! call_user_func($widget['condition'], $user)) {
                return false;
            }
        }

        $settings = self::getSettings();
        $key = $widget['key'];

        if ($user->isSuperAdmin()) {
            return (bool) ($settings['super_admin'][$key] ?? false);
        }

        if ($user->hasRole(RoleName::MANAGER->value)) {
            // Code allow-list: Admin-only widgets are strictly denied for Manager
            if ($widget['admin_only']) {
                return false;
            }

            return (bool) ($settings['manager'][$key] ?? false);
        }

        return false;
    }

    /**
     * Return active widget class list in the exact registered order for a user.
     */
    public static function getEnabledWidgetsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $enabled = [];
        foreach (self::getRegisteredWidgets() as $item) {
            if (self::isWidgetVisibleForUser($item['class'], $user)) {
                $enabled[] = $item['class'];
            }
        }

        return $enabled;
    }
}
