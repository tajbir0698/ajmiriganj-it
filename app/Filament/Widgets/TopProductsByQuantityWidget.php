<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Services\DashboardWidgetRegistry;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProductsByQuantityWidget extends BaseWidget
{
    protected static ?int $sort = 6;

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
            ->heading('Top 5 Products by Quantity (Last 30 Days)')
            ->description('Ranked strictly by unit sales volume.')
            ->query(
                Product::query()
                    ->join('sale_items', 'products.id', '=', 'sale_items.product_id')
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.status', SaleStatus::COMPLETED)
                    ->whereDate('sales.sale_date', '>=', $thirtyDaysAgo)
                    ->selectRaw('
                        products.id,
                        products.name as product_name,
                        products.sku as product_sku,
                        COALESCE(SUM(sale_items.qty), 0) as total_qty_sold,
                        COALESCE(SUM(sale_items.line_total), 0) as total_revenue
                    ')
                    ->groupBy('products.id', 'products.name', 'products.sku')
                    ->orderByDesc('total_qty_sold')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->description(fn ($record) => "SKU: {$record->product_sku}"),

                TextColumn::make('total_qty_sold')
                    ->label('Units Sold')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::formatQty((string) $state)),

                TextColumn::make('total_revenue')
                    ->label('Sales Volume')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state)),
            ])
            ->paginated(false);
    }
}
