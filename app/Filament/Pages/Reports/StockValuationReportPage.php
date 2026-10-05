<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\StockValuationReportExport;
use App\Models\Category;
use App\Services\Reports\StockValuationReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockValuationReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Stock Valuation (FIFO)';

    protected string $view = 'filament.pages.reports.stock-valuation-report';

    public ?int $categoryId = null;

    public ?string $stockStatus = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->categoryId = request('category_id') ? (int) request('category_id') : null;
        $this->stockStatus = request('stock_status');
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDataProperty(): array
    {
        /** @var StockValuationReportService $service */
        $service = app(StockValuationReportService::class);

        return $service->generate([
            'category_id' => $this->categoryId,
            'stock_status' => $this->stockStatus,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.stock-valuation', array_filter([
                    'category_id' => $this->categoryId,
                    'stock_status' => $this->stockStatus,
                ])))
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): BinaryFileResponse {
                    $data = $this->reportData;
                    // Flatten products across categories for flat export
                    $allProducts = collect();
                    foreach ($data['categories'] as $cat) {
                        foreach ($cat['products'] as $p) {
                            $p['category_name'] = $cat['category_name'];
                            $allProducts->push($p);
                        }
                    }

                    $filename = sprintf('stock_valuation_%s.xlsx', now()->format('Ymd_His'));

                    return Excel::download(new StockValuationReportExport($allProducts), $filename);
                }),
        ];
    }
}
