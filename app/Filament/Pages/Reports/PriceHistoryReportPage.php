<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Product;
use App\Services\Reports\PriceHistoryReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PriceHistoryReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'Price History & Vendor Comparison';

    protected string $view = 'filament.pages.reports.price-history-report';

    public ?int $productId = null;

    public string $viewType = 'changes'; // changes or vendor_comparison

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->productId = request('product_id') ? (int) request('product_id') : null;
        $this->viewType = request('view_type', 'changes');
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var PriceHistoryReportService $service */
        $service = app(PriceHistoryReportService::class);

        return $service->generate([
            'product_id' => $this->productId,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.price-history', array_filter([
                    'product_id' => $this->productId,
                ])))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $data = $this->reportData;
                    $filename = sprintf('price_history_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PriceHistoryReportExport($data['rows']), $filename);
                }),
        ];
    }
}
