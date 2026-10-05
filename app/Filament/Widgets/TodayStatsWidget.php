<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\BusinessFinanceService;
use App\Services\DashboardWidgetRegistry;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    protected function getStats(): array
    {
        $todayStr = now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->toDateString();
        $startToday = now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->startOfDay();
        $endToday = now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->endOfDay();

        $salesToday = Sale::query()
            ->whereDate('sale_date', $todayStr)
            ->where('status', SaleStatus::COMPLETED)
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as total_sales,
                COALESCE(SUM(paid_amount), 0) as total_collected
            ')
            ->first();

        $salesCount = (int) ($salesToday->count ?? 0);
        $totalSales = (string) ($salesToday->total_sales ?? '0.00');
        $totalCollected = (string) ($salesToday->total_collected ?? '0.00');

        $financeService = app(BusinessFinanceService::class);
        $todayNetProfit = $financeService->netProfit($startToday, $endToday);

        $newCustomersToday = Customer::query()
            ->whereDate('created_at', $todayStr)
            ->count();

        return [
            Stat::make("Today's Sales Count", (string) $salesCount)
                ->description('Completed orders today')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('primary'),

            Stat::make("Today's Sales Total", '৳ '.Money::format($totalSales))
                ->description('Total revenue invoiced')
                ->descriptionIcon('heroicon-o-currency-bangladeshi')
                ->color('success'),

            Stat::make("Today's Cash Collected", '৳ '.Money::format($totalCollected))
                ->description('POS payments received')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make("Today's Net Profit", '৳ '.Money::format($todayNetProfit))
                ->description('Accrual profit after expenses & returns')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color(bccomp($todayNetProfit, '0.00', 2) >= 0 ? 'success' : 'danger'),

            Stat::make('New Customers', (string) $newCustomersToday)
                ->description('Registered today')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('secondary'),
        ];
    }
}
