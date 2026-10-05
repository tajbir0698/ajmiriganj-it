<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (StockMovement $record): string => "SKU: {$record->product?->sku}"),

                TextColumn::make('type')
                    ->label('Movement Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof StockMovementType ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state) => match ($state instanceof StockMovementType ? $state->value : (string) $state) {
                        'purchase', 'opening', 'sale_return' => 'success',
                        'sale' => 'info',
                        'adjustment' => 'warning',
                        'damage', 'purchase_return' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('qty')
                    ->label('Quantity')
                    ->alignRight()
                    ->state(fn (StockMovement $record): string => ($record->isIncrease() ? '+' : '').Money::formatQty((string) $record->qty).' '.($record->product?->unit?->short_name ?? ''))
                    ->color(fn (StockMovement $record): string => $record->isIncrease() ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('Landed Cost (৳)')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->state(function (StockMovement $record): string {
                        if (! $record->reference_type || ! $record->reference_id) {
                            return $record->purchase_item_id ? "Batch #{$record->purchase_item_id}" : '-';
                        }
                        $basename = class_basename($record->reference_type);

                        return "{$basename} #{$record->reference_id}";
                    }),

                TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->options(fn () => Product::query()->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        StockMovementType::PURCHASE->value => 'Purchase',
                        StockMovementType::OPENING->value => 'Opening Stock',
                        StockMovementType::SALE->value => 'Sale',
                        StockMovementType::ADJUSTMENT->value => 'Adjustment',
                        StockMovementType::DAMAGE->value => 'Damage',
                        StockMovementType::SALE_RETURN->value => 'Sale Return',
                        StockMovementType::PURCHASE_RETURN->value => 'Purchase Return',
                    ]),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
