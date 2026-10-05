<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\DailySummaryReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class DailySummaryReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Daily Summary & EOD Close';

    protected string $view = 'filament.pages.reports.daily-summary-report';

    public ?string $date = null;

    public bool $thermalMode = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->date = request('date', now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->toDateString());
        $this->thermalMode = (bool) request('thermal', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var DailySummaryReportService $service */
        $service = app(DailySummaryReportService::class);

        return $service->generate($this->date);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('thermal_print')
                ->label('80mm Thermal Receipt')
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->url(fn (): string => route('reports.print.daily-summary', ['date' => $this->date ?? date('Y-m-d'), 'format' => '80mm']))
                ->openUrlInNewTab(),

            Action::make('print')
                ->label('Print A4 Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.daily-summary', ['date' => $this->date ?? date('Y-m-d'), 'format' => 'a4']))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('daily_summary_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DailySummaryReportExport($data), $filename);
                }),
        ];
    }
}
