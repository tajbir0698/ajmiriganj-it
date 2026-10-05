<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\PurchaseReportExport;
use App\Models\Vendor;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\ReportPeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Purchase Report';

    protected string $view = 'filament.pages.reports.purchase-report';

    public string $period = 'this_month';

    public ?string $from = null;

    public ?string $to = null;

    public ?int $vendorId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->period = request('period', 'this_month');
        $this->from = request('from');
        $this->to = request('to');
        $this->vendorId = request('vendor_id') ? (int) request('vendor_id') : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var PurchaseReportService $service */
        $service = app(PurchaseReportService::class);
        $reportPeriod = ReportPeriod::fromPreset($this->period, $this->from, $this->to);

        $filters = [
            'vendor_id' => $this->vendorId,
        ];

        return $service->generate($reportPeriod, $filters);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.purchases', array_filter([
                    'period' => $this->period,
                    'from' => $this->from,
                    'to' => $this->to,
                    'vendor_id' => $this->vendorId,
                ])))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('purchase_report_%s.xlsx', now()->format('Ymd_His'));

                    return Excel::download(new PurchaseReportExport($data['rows']), $filename);
                }),
        ];
    }
}
