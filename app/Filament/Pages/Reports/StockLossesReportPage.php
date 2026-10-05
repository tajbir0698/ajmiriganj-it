<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\ReportPeriod;
use App\Services\Reports\StockLossesReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class StockLossesReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Stock Losses & Adjustments';

    protected string $view = 'filament.pages.reports.stock-losses-report';

    public string $period = 'this_month';

    public ?string $from = null;

    public ?string $to = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->period = request('period', 'this_month');
        $this->from = request('from');
        $this->to = request('to');
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var StockLossesReportService $service */
        $service = app(StockLossesReportService::class);
        $reportPeriod = ReportPeriod::fromPreset($this->period, $this->from, $this->to);

        return $service->generate([
            'start_date' => $reportPeriod->fromDateString(),
            'end_date' => $reportPeriod->toDateString(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.stock-losses', array_filter([
                    'period' => $this->period,
                    'from' => $this->from,
                    'to' => $this->to,
                ])))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('stock_losses_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\StockLossesReportExport($data['adjustments'], $data['totals']), $filename);
                }),
        ];
    }
}
