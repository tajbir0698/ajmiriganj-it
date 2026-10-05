<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\BalanceSheetReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class BalanceSheetReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Balance Sheet';

    protected string $view = 'filament.pages.reports.balance-sheet-report';

    public ?string $asOfDate = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->asOfDate = request('as_of_date', now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var BalanceSheetReportService $service */
        $service = app(BalanceSheetReportService::class);

        return $service->generate($this->asOfDate);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Statement')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.balance-sheet', ['as_of' => $this->asOfDate]))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('balance_sheet_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BalanceSheetReportExport($data), $filename);
                }),
        ];
    }
}
