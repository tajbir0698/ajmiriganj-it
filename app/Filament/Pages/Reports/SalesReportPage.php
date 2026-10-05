<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\SalesReportExport;
use App\Models\Customer;
use App\Models\User;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Sales Report';

    protected string $view = 'filament.pages.reports.sales-report';

    public string $period = 'this_month';

    public ?string $from = null;

    public ?string $to = null;

    public ?int $customerId = null;

    public ?int $cashierId = null;

    public ?string $paymentMethod = null;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $this->period = request('period', 'this_month');
        $this->from = request('from');
        $this->to = request('to');
        $this->customerId = request('customer_id') ? (int) request('customer_id') : null;
        $this->cashierId = request('cashier_id') ? (int) request('cashier_id') : null;
        $this->paymentMethod = request('payment_method');
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var SalesReportService $service */
        $service = app(SalesReportService::class);
        $reportPeriod = ReportPeriod::fromPreset($this->period, $this->from, $this->to);

        $filters = [
            'customer_id' => $this->customerId,
            'cashier_id' => $this->cashierId,
            'payment_method' => $this->paymentMethod,
        ];

        return $service->generate($reportPeriod, $filters, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.sales', array_filter([
                    'period' => $this->period,
                    'from' => $this->from,
                    'to' => $this->to,
                    'customer_id' => $this->customerId,
                    'cashier_id' => $this->cashierId,
                    'payment_method' => $this->paymentMethod,
                ])))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('sales_report_%s.xlsx', now()->format('Ymd_His'));
                    $isManager = ! auth()->user()?->isSuperAdmin();

                    return Excel::download(new SalesReportExport($data['rows'], $isManager), $filename);
                }),
        ];
    }
}
