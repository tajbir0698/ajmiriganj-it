<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\DashboardWidgetRegistry;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class SalesTrendChartWidget extends ChartWidget
{
    protected ?string $heading = '30-Day Sales Trend';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    protected function getData(): array
    {
        $tz = config('app.timezone', 'Asia/Dhaka');
        $startDate = now()->setTimezone($tz)->subDays(29)->startOfDay();
        $endDate = now()->setTimezone($tz)->endOfDay();

        $sales = Sale::query()
            ->where('status', SaleStatus::COMPLETED)
            ->whereDate('sale_date', '>=', $startDate->toDateString())
            ->whereDate('sale_date', '<=', $endDate->toDateString())
            ->selectRaw('DATE(sale_date) as date, COALESCE(SUM(total), 0) as daily_total')
            ->groupBy('date')
            ->pluck('daily_total', 'date');

        $labels = [];
        $data = [];

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            $labels[] = $current->format('M d');
            $data[] = (float) ($sales->get($dateStr) ?? 0);
            $current->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily Sales (৳)',
                    'data' => $data,
                    'fill' => 'start',
                    'borderColor' => '#0284c7',
                    'backgroundColor' => 'rgba(2, 132, 199, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
