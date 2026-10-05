<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\SaleItem;
use App\Services\DashboardWidgetRegistry;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProductsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    public function table(Table $table): Table
    {
        $tz = config('app.timezone', 'Asia/Dhaka');
        $thirtyDaysAgo = now()->setTimezone($tz)->subDays(30)->toDateString();

        return $table
            ->heading('Top Selling Products (Last 30 Days)')
            ->description('Ranked by total revenue and contribution.')
            ->query(
                \App\Models\Product::query()
                    ->join('sale_items', 'products.id', '=', 'sale_items.product_id')
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.status', SaleStatus::COMPLETED)
                    ->whereDate('sales.sale_date', '>=', $thirtyDaysAgo)
                    ->selectRaw('
                        products.id,
                        products.name as product_name,
                        products.sku as product_sku,
                        COALESCE(SUM(sale_items.qty), 0) as total_qty_sold,
                        COALESCE(SUM(sale_items.line_total), 0) as total_revenue,
                        COALESCE(SUM(sale_items.line_profit), 0) as total_profit
                    ')
                    ->groupBy('products.id', 'products.name', 'products.sku')
                    ->orderByDesc('total_revenue')
            )
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->description(fn ($record) => "SKU: {$record->product_sku}"),

                TextColumn::make('total_qty_sold')
                    ->label('Qty Sold')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::formatQty((string) $state)),

                TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state)),

                TextColumn::make('total_profit')
                    ->label('Gross Profit')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->color('success'),
            ])
            ->paginated(false);
    }
}
