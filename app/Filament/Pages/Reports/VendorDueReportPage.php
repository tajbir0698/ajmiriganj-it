<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\VendorDueReportExport;
use App\Services\Reports\VendorAgingReportService;
use App\Services\Reports\VendorDueReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VendorDueReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Vendor Due & Aging';

    protected string $view = 'filament.pages.reports.vendor-due-report';

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
        /** @var VendorDueReportService $service */
        $service = app(VendorDueReportService::class);

        return $service->generate();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgingDataProperty(): array
    {
        /** @var VendorAgingReportService $service */
        $service = app(VendorAgingReportService::class);

        return $service->generate();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.vendor-due'))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): BinaryFileResponse {
                    if ($this->tab === 'aging') {
                        $data = $this->agingData;
                        $filename = sprintf('vendor_aging_%s.xlsx', now()->format('Ymd_His'));

                        return Excel::download(new \App\Exports\VendorAgingReportExport($data['rows'], $data['totals']), $filename);
                    }

                    $data = $this->summaryData;
                    $filename = sprintf('vendor_due_%s.xlsx', now()->format('Ymd_His'));

                    return Excel::download(new VendorDueReportExport($data['rows']), $filename);
                }),
        ];
    }
}
