<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\DashboardWidgetRegistry;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ManagerTodayStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $todayStr = now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->toDateString();
        $visibility = \App\Models\Setting::get('manager_sales_visibility', 'all');

        $query = Sale::query()
            ->whereDate('sale_date', $todayStr)
            ->where('status', SaleStatus::COMPLETED);

        if ($visibility === 'own') {
            $query->where('created_by', $user?->id);
        }

        $salesAgg = $query
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as total_sales,
                COALESCE(SUM(paid_amount), 0) as total_collected
            ')
            ->first();

        $count = (int) ($salesAgg->count ?? 0);
        $totalSales = (string) ($salesAgg->total_sales ?? '0.00');
        $totalCollected = (string) ($salesAgg->total_collected ?? '0.00');

        if ($visibility === 'all') {
            $breakdown = Sale::query()
                ->whereDate('sale_date', $todayStr)
                ->where('status', SaleStatus::COMPLETED)
                ->with('creator')
                ->selectRaw('created_by, COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total_collected')
                ->groupBy('created_by')
                ->get()
                ->map(fn ($b) => ($b->creator?->name ?? 'Staff').': '.$b->count.' (৳'.Money::format((string) $b->total_collected).')')
                ->join(' • ');

            $descBreakdown = ! empty($breakdown) ? $breakdown : 'All cashiers today';

            return [
                Stat::make("Today's sales", (string) $count)
                    ->description($descBreakdown)
                    ->descriptionIcon('heroicon-o-shopping-bag')
                    ->color('primary'),

                Stat::make("Today's Invoiced Total", '৳ '.Money::format($totalSales))
                    ->description('Total value of all sales today')
                    ->descriptionIcon('heroicon-o-currency-bangladeshi')
                    ->color('success'),

                Stat::make("Today's Collected Cash", '৳ '.Money::format($totalCollected))
                    ->description('Total payments collected today')
                    ->descriptionIcon('heroicon-o-banknotes')
                    ->color('info'),

                Stat::make("Open POS Terminal", "Launch Register")
                    ->description('Start new customer sale')
                    ->descriptionIcon('heroicon-o-computer-desktop')
                    ->color('warning')
                    ->url(url('/pos')),
            ];
        }

        return [
            Stat::make("My Sales Today", (string) $count)
                ->description('Completed invoices entered by you')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('primary'),

            Stat::make("My Invoiced Total", '৳ '.Money::format($totalSales))
                ->description('Total value of your sales today')
                ->descriptionIcon('heroicon-o-currency-bangladeshi')
                ->color('success'),

            Stat::make("My Collected Cash", '৳ '.Money::format($totalCollected))
                ->description('Payments received at counter')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make("Open POS Terminal", "Launch Register")
                ->description('Start new customer sale')
                ->descriptionIcon('heroicon-o-computer-desktop')
                ->color('warning')
                ->url(url('/pos')),
        ];
    }
}
