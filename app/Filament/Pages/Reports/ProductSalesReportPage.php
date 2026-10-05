<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\ProductSalesReportExport;
use App\Models\Category;
use App\Models\Product;
use App\Services\Reports\ProductSalesReportService;
use App\Services\Reports\ReportPeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductSalesReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Product Sales & Profit';

    protected string $view = 'filament.pages.reports.product-sales-report';

    public string $period = 'this_month';

    public ?string $from = null;

    public ?string $to = null;

    public ?int $categoryId = null;

    public ?int $productId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->period = request('period', 'this_month');
        $this->from = request('from');
        $this->to = request('to');
        $this->categoryId = request('category_id') ? (int) request('category_id') : null;
        $this->productId = request('product_id') ? (int) request('product_id') : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var ProductSalesReportService $service */
        $service = app(ProductSalesReportService::class);
        $reportPeriod = ReportPeriod::fromPreset($this->period, $this->from, $this->to);

        $filters = [
            'category_id' => $this->categoryId,
            'product_id' => $this->productId,
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
                ->url(fn (): string => route('reports.print.product-sales', array_filter([
                    'period' => $this->period,
                    'from' => $this->from,
                    'to' => $this->to,
                    'category_id' => $this->categoryId,
                    'product_id' => $this->productId,
                ])))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('product_sales_profit_%s.xlsx', now()->format('Ymd_His'));

                    return Excel::download(new ProductSalesReportExport($data['rows']), $filename);
                }),
        ];
    }
}
