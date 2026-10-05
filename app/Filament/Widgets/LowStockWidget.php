<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Support\Money;
use App\Services\DashboardWidgetRegistry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low Stock & Out of Stock Alerts')
            ->description('Products requiring immediate replenishment.')
            ->query(
                Product::query()
                    ->safeForManager()
                    ->with('unit')
                    ->lowOrOutOfStock()
                    ->orderBy('stock_qty', 'asc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->description(fn (Product $record): string => "SKU: {$record->sku}"),

                TextColumn::make('stock_qty')
                    ->label('Current Stock')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, Product $record) => Money::formatQty((string) $state).' '.($record->unit?->short_name ?? ''))
                    ->weight('bold')
                    ->color(fn (Product $record) => $record->isOutOfStock() ? 'danger' : 'warning'),

                TextColumn::make('alert_qty')
                    ->label('Alert Threshold')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, Product $record) => Money::formatQty((string) $state).' '.($record->unit?->short_name ?? '')),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->state(fn (Product $record): string => $record->isOutOfStock() ? 'Out of Stock' : 'Low Stock')
                    ->color(fn (string $state): string => $state === 'Out of Stock' ? 'danger' : 'warning'),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
