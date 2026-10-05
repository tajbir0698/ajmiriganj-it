<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockAdjustments;

use App\Enums\AdjustmentType;
use App\Filament\Resources\StockAdjustments\Pages\CreateStockAdjustment;
use App\Filament\Resources\StockAdjustments\Pages\ListStockAdjustments;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return false; // Immutable audit log
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Immutable audit log
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Stock Adjustment Entry')
                    ->description('Adjust inventory levels. Decreases and damages consume FIFO batches; increases create a new FIFO batch.')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => Product::query()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if ($product = Product::find($state)) {
                                    $set('unit_cost', $product->last_cost > 0 ? $product->last_cost : 0.00);
                                }
                            }),

                        Select::make('type')
                            ->label('Adjustment Type')
                            ->options([
                                AdjustmentType::INCREASE->value => 'Stock Increase (+) - Found / Re-counted',
                                AdjustmentType::DECREASE->value => 'Stock Decrease (-) - Missing / Shrinkage',
                                AdjustmentType::DAMAGE->value => 'Damaged Goods (-) - Broken / Expired',
                            ])
                            ->default(AdjustmentType::INCREASE->value)
                            ->required()
                            ->live(),

                        TextInput::make('qty')
                            ->label('Quantity to Adjust')
                            ->numeric()
                            ->minValue(0.001)
                            ->default(1)
                            ->required(),

                        TextInput::make('unit_cost')
                            ->label('Unit Cost (৳)')
                            ->numeric()
                            ->prefix('৳')
                            ->required(fn ($get): bool => $get('type') === AdjustmentType::INCREASE->value)
                            ->visible(fn ($get): bool => $get('type') === AdjustmentType::INCREASE->value)
                            ->helperText('Required for stock increases to value the new batch.'),

                        DatePicker::make('adjusted_at')
                            ->label('Adjustment Date')
                            ->default(now())
                            ->required(),

                        Textarea::make('reason')
                            ->label('Reason / Audit Explanation')
                            ->placeholder('e.g. Physical inventory count variance, transit carton damage, etc.')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adjustment_no')
                    ->label('Adjustment #')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                TextColumn::make('adjusted_at')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (StockAdjustment $record): string => "SKU: {$record->product?->sku}"),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AdjustmentType ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state) => match ($state instanceof AdjustmentType ? $state->value : (string) $state) {
                        'increase' => 'success',
                        'decrease' => 'warning',
                        'damage' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('qty')
                    ->label('Qty')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, StockAdjustment $record) => Money::formatQty((string) $state).' '.($record->product?->unit?->short_name ?? '')),

                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => $state ? Money::format((string) $state) : '-'),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->weight('bold'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(35)
                    ->tooltip(fn (StockAdjustment $record): string => $record->reason),

                TextColumn::make('creator.name')
                    ->label('Adjusted By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        AdjustmentType::INCREASE->value => 'Increase',
                        AdjustmentType::DECREASE->value => 'Decrease',
                        AdjustmentType::DAMAGE->value => 'Damage',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockAdjustments::route('/'),
            'create' => CreateStockAdjustment::route('/create'),
        ];
    }
}
