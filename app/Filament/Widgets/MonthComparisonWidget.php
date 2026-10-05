<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\BusinessFinanceService;
use App\Services\DashboardWidgetRegistry;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthComparisonWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    protected function getStats(): array
    {
        $tz = config('app.timezone', 'Asia/Dhaka');
        $now = now()->setTimezone($tz);

        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();

        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // 1. Sales
        $thisMonthSales = (string) Sale::where('status', SaleStatus::COMPLETED)
            ->whereDate('sale_date', '>=', $thisMonthStart->toDateString())
            ->whereDate('sale_date', '<=', $thisMonthEnd->toDateString())
            ->sum('total');

        $lastMonthSales = (string) Sale::where('status', SaleStatus::COMPLETED)
            ->whereDate('sale_date', '>=', $lastMonthStart->toDateString())
            ->whereDate('sale_date', '<=', $lastMonthEnd->toDateString())
            ->sum('total');

        // 2. Expenses & Profit
        $financeService = app(BusinessFinanceService::class);
        $thisMonthExp = $financeService->operatingExpenses($thisMonthStart, $thisMonthEnd);
        $lastMonthExp = $financeService->operatingExpenses($lastMonthStart, $lastMonthEnd);

        $thisMonthProfit = $financeService->netProfit($thisMonthStart, $thisMonthEnd);
        $lastMonthProfit = $financeService->netProfit($lastMonthStart, $lastMonthEnd);

        // Helper to format change
        $calcChange = function (string $current, string $previous): string {
            if (bccomp($previous, '0.00', 2) === 0) {
                return bccomp($current, '0.00', 2) > 0 ? '+100%' : '0%';
            }
            $diff = bcsub($current, $previous, 2);
            $pct = bcdiv(bcmul($diff, '100.00', 2), $previous, 1);

            return (bccomp($pct, '0.0', 1) >= 0 ? '+' : '').$pct.'%';
        };

        $salesChange = $calcChange($thisMonthSales, $lastMonthSales);
        $expChange = $calcChange($thisMonthExp, $lastMonthExp);
        $profitChange = $calcChange($thisMonthProfit, $lastMonthProfit);

        return [
            Stat::make('Sales (This Month)', '৳ '.Money::format($thisMonthSales))
                ->description("Last month: ৳ ".Money::format($lastMonthSales)." ({$salesChange})")
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color(bccomp($thisMonthSales, $lastMonthSales, 2) >= 0 ? 'success' : 'warning'),

            Stat::make('Expenses (This Month)', '৳ '.Money::format($thisMonthExp))
                ->description("Last month: ৳ ".Money::format($lastMonthExp)." ({$expChange})")
                ->descriptionIcon('heroicon-o-credit-card')
                ->color(bccomp($thisMonthExp, $lastMonthExp, 2) <= 0 ? 'success' : 'danger'),

            Stat::make('Net Profit (This Month)', '৳ '.Money::format($thisMonthProfit))
                ->description("Last month: ৳ ".Money::format($lastMonthProfit)." ({$profitChange})")
                ->descriptionIcon('heroicon-o-banknotes')
                ->color(bccomp($thisMonthProfit, '0.00', 2) >= 0 ? 'success' : 'danger'),
        ];
    }
}
