<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\IncomeExpenseReportService;
use App\Services\Reports\ReportPeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class IncomeExpenseReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 16;

    protected static ?string $title = 'Income & Expense Analysis';

    protected string $view = 'filament.pages.reports.income-expense-report';

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
        /** @var IncomeExpenseReportService $service */
        $service = app(IncomeExpenseReportService::class);
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
                ->url(fn (): string => route('reports.print.income-expense', array_filter([
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
                    $filename = sprintf('income_expense_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\IncomeExpenseReportExport($data), $filename);
                }),
        ];
    }
}
