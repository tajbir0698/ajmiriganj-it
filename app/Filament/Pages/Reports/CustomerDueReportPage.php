<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\CustomerDueReportExport;
use App\Services\Reports\CustomerAgingReportService;
use App\Services\Reports\CustomerDueReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerDueReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Customer Due & Aging';

    protected string $view = 'filament.pages.reports.customer-due-report';

    public string $viewType = 'summary'; // summary or aging

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->viewType = request('view_type', 'summary');
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummaryDataProperty(): array
    {
        /** @var CustomerDueReportService $service */
        $service = app(CustomerDueReportService::class);

        return $service->generate();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgingDataProperty(): array
    {
        /** @var CustomerAgingReportService $service */
        $service = app(CustomerAgingReportService::class);

        return $service->generate();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.customer-due'))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): BinaryFileResponse {
                    if ($this->tab === 'aging') {
                        $data = $this->agingData;
                        $filename = sprintf('customer_aging_%s.xlsx', now()->format('Ymd_His'));

                        return Excel::download(new \App\Exports\CustomerAgingReportExport($data['rows'], $data['totals']), $filename);
                    }

                    $data = $this->summaryData;
                    $filename = sprintf('customer_due_%s.xlsx', now()->format('Ymd_His'));

                    return Excel::download(new CustomerDueReportExport($data['rows']), $filename);
                }),
        ];
    }
}
