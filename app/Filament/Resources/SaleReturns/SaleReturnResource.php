<?php

declare(strict_types=1);

namespace App\Filament\Resources\SaleReturns;

use App\Enums\PaymentMethod;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Filament\Resources\SaleReturns\Pages\CreateSaleReturn;
use App\Filament\Resources\SaleReturns\Pages\ListSaleReturns;
use App\Filament\Resources\SaleReturns\Pages\ViewSaleReturn;
use App\Models\Account;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleReturnResource extends Resource
{
    protected static ?string $model = SaleReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sale & Return Details')
                    ->schema([
                        Select::make('sale_id')
                            ->label('Sale Invoice')
                            ->options(
                                fn () => Sale::where('status', SaleStatus::COMPLETED)
                                    ->where(function ($q) {
                                        $q->whereNull('return_status')
                                          ->orWhere('return_status', '!=', ReturnStatus::FULL->value);
                                    })
                                    ->latest('id')
                                    ->pluck('invoice_no', 'id')
                            )
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('items', [])),

                        DatePicker::make('return_date')
                            ->label('Return Date')
                            ->default(now()->toDateString())
                            ->required(),

                        Toggle::make('return_whole_sale')
                            ->label('Return Entire Sale')
                            ->helperText('Automatically returns all remaining quantities of all items in this sale')
                            ->default(false)
                            ->reactive()
                            ->rules([
                                fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (! $value) {
                                        return;
                                    }
                                    $saleId = $get('sale_id');
                                    if (! $saleId) {
                                        return;
                                    }
                                    $hasReturnable = SaleItem::where('sale_id', $saleId)
                                        ->get()
                                        ->contains(fn ($item) => bccomp($item->returnableQty(), '0.000', 3) > 0);

                                    if (! $hasReturnable) {
                                        $fail('This sale has no returnable items remaining.');
                                    }
                                },
                            ]),

                        Select::make('refund_account_id')
                            ->label('Refund Cash From Account')
                            ->helperText('Required if the return value exceeds the unpaid due on the sale')
                            ->options(fn () => Account::where('is_active', true)->pluck('name', 'id')),

                        Select::make('refund_payment_method')
                            ->label('Payment Method')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                            ->default(PaymentMethod::CASH->value),

                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Items to Return')
                    ->visible(fn ($get) => ! $get('return_whole_sale'))
                    ->schema([
                        Repeater::make('items')
                            ->schema([
                                Select::make('sale_item_id')
                                    ->label('Item')
                                    ->options(function ($get) {
                                        $saleId = $get('../../sale_id');
                                        if (! $saleId) {
                                            return [];
                                        }

                                        return SaleItem::where('sale_id', $saleId)
                                            ->get()
                                            ->filter(fn ($item) => bccomp($item->returnableQty(), '0.000', 3) > 0)
                                            ->mapWithKeys(fn ($item) => [
                                                $item->id => "{$item->product_name} (Sold: {$item->qty}, Returnable: {$item->returnableQty()})",
                                            ]);
                                    })
                                    ->required()
                                    ->distinct()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set) {
                                        if ($state) {
                                            $item = SaleItem::find($state);
                                            if ($item) {
                                                $set('qty', (string) $item->returnableQty());
                                            }
                                        }
                                    }),

                                TextInput::make('qty')
                                    ->label('Return Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->rules([
                                        fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $itemId = $get('sale_item_id');
                                            if (! $itemId) {
                                                return;
                                            }
                                            $item = SaleItem::find($itemId);
                                            if (! $item) {
                                                return;
                                            }
                                            $returnable = $item->returnableQty();
                                            if (bccomp((string) $value, $returnable, 3) > 0) {
                                                $fail("Quantity cannot exceed returnable amount ({$returnable}).");
                                            }
                                        },
                                    ]),
                            ])
                            ->columns(2)
                            ->minItems(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_no')
                    ->label('Return No')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('return_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('sale.invoice_no')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->default('Walk-in Customer')
                    ->searchable(),

                TextColumn::make('refund_amount')
                    ->label('Total Refund')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->weight('bold'),

                TextColumn::make('due_reduction')
                    ->label('Due Reduced')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state)),

                TextColumn::make('cash_refund')
                    ->label('Cash Paid')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state)),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->url(fn (SaleReturn $record): string => route('sale-returns.receipt', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSaleReturns::route('/'),
            'create' => CreateSaleReturn::route('/create'),
            'view' => ViewSaleReturn::route('/{record}'),
        ];
    }
}
