<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockOverview;

use App\Filament\Resources\StockOverview\Pages\ListStockOverview;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\FifoStockService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockOverviewResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'stock-overview';

    protected static ?string $modelLabel = 'Stock Overview';

    protected static ?string $pluralModelLabel = 'Stock Overview';

    protected static ?string $navigationLabel = 'Stock Overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        // Visible to both Super Admin and Manager
        return $user !== null;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Product::where('is_active', true)->lowOrOutOfStock()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['category', 'unit']);

        /** @var User|null $user */
        $user = auth()->user();

        // Strict server-side cost redaction for Manager:
        // NEVER select last_cost or expose cost columns
        if ($user && ! $user->isSuperAdmin()) {
            return $query->safeForManager();
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record): string => "SKU: {$record->sku}".($record->barcode ? " | Barcode: {$record->barcode}" : '')),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('stock_qty')
                    ->label('Stock Qty')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, Product $record) => Money::formatQty((string) $state).' '.($record->unit?->short_name ?? ''))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->state(function (Product $record): string {
                        if ($record->isOutOfStock()) {
                            return 'Out of Stock';
                        }
                        if ($record->isLowStock()) {
                            return 'Low Stock';
                        }

                        return 'In Stock';
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            'Out of Stock' => 'danger',
                            'Low Stock' => 'warning',
                            default => 'success',
                        };
                    }),

                TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->sortable(),

                // Super Admin ONLY columns (Strictly hidden from Manager)
                TextColumn::make('last_cost')
                    ->label('Last Cost')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->visible($isSuperAdmin)
                    ->sortable(),

                TextColumn::make('stock_valuation')
                    ->label('Stock Value (FIFO)')
                    ->alignRight()
                    ->state(function (Product $record) use ($isSuperAdmin): string {
                        if (! $isSuperAdmin) {
                            return '';
                        }

                        return Money::format(app(FifoStockService::class)->stockValue($record));
                    })
                    ->visible($isSuperAdmin),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::pluck('name', 'id')),

                Filter::make('low_stock')
                    ->label('Low Stock (<= Alert Qty)')
                    ->query(fn (Builder $query): Builder => $query->lowStock()),

                Filter::make('out_of_stock')
                    ->label('Out of Stock (<= 0)')
                    ->query(fn (Builder $query): Builder => $query->outOfStock()),
            ])
            ->actions([
                // Super Admin can view open batches modal
                Action::make('viewBatches')
                    ->label('Batches')
                    ->icon('heroicon-o-queue-list')
                    ->color('primary')
                    ->visible($isSuperAdmin)
                    ->modalHeading(fn (Product $record): string => "FIFO Batches: {$record->name}")
                    ->modalDescription('Active FIFO batches currently in stock, ordered by arrival date.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (Product $record) {
                        $batches = PurchaseItem::where('product_id', $record->id)
                            ->where('remaining_qty', '>', 0)
                            ->with(['purchase.vendor'])
                            ->orderBy('batch_date', 'asc')
                            ->orderBy('id', 'asc')
                            ->get();

                        return view('filament.resources.products.batches-modal', [
                            'product' => $record,
                            'batches' => $batches,
                        ]);
                    }),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockOverview::route('/'),
        ];
    }
}
