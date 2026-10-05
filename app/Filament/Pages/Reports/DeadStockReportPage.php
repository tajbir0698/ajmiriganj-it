<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Category;
use App\Models\Setting;
use App\Services\Reports\DeadStockReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class DeadStockReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Dead Stock Report';

    protected string $view = 'filament.pages.reports.dead-stock-report';

    public int $days = 60;

    public ?int $categoryId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $defaultDays = (int) Setting::get('dead_stock_days', 60);
        $this->days = request('days') ? (int) request('days') : $defaultDays;
        $this->categoryId = request('category_id') ? (int) request('category_id') : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var DeadStockReportService $service */
        $service = app(DeadStockReportService::class);

        return $service->generate([
            'days' => $this->days,
            'category_id' => $this->categoryId,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.dead-stock', array_filter([
                    'days' => $this->days,
                    'category_id' => $this->categoryId,
                ])))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('dead_stock_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DeadStockReportExport($data['rows'], $data['totals']), $filename);
                }),
        ];
    }
}
