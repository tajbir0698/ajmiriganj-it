<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\ProfitAndLossReportService;
use App\Services\Reports\ReportPeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ProfitAndLossReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Profit & Loss Statement';

    protected string $view = 'filament.pages.reports.profit-and-loss-report';

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
        /** @var ProfitAndLossReportService $service */
        $service = app(ProfitAndLossReportService::class);
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
                ->label('Print Statement')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.profit-and-loss', array_filter([
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
                    $filename = sprintf('profit_and_loss_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ProfitAndLossReportExport($data), $filename);
                }),
        ];
    }
}
