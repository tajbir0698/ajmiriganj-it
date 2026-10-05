<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturns;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\ReturnSettlement;
use App\Filament\Resources\PurchaseReturns\Pages\CreatePurchaseReturn;
use App\Filament\Resources\PurchaseReturns\Pages\ListPurchaseReturns;
use App\Filament\Resources\PurchaseReturns\Pages\ViewPurchaseReturn;
use App\Models\Account;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

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
                Section::make('Return Information')
                    ->schema([
                        Select::make('purchase_id')
                            ->label('Purchase Bill')
                            ->options(
                                fn () => Purchase::where('status', PurchaseStatus::ACTIVE)
                                    ->whereHas('items', fn ($q) => $q->where('remaining_qty', '>', 0))
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

                        Select::make('settlement')
                            ->label('Settlement Mode')
                            ->options([
                                ReturnSettlement::REDUCE_DUE_CREDIT->value => 'Reduce Due / Vendor Credit',
                                ReturnSettlement::REFUND_RECEIVED->value => 'Refund Received (Cash/Bank)',
                            ])
                            ->default(ReturnSettlement::REDUCE_DUE_CREDIT->value)
                            ->required()
                            ->reactive(),

                        Select::make('refund_account_id')
                            ->label('Deposit To Account')
                            ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn ($get) => $get('settlement') === ReturnSettlement::REFUND_RECEIVED->value)
                            ->required(fn ($get) => $get('settlement') === ReturnSettlement::REFUND_RECEIVED->value),

                        Select::make('refund_payment_method')
                            ->label('Payment Method')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                            ->default(PaymentMethod::CASH->value)
                            ->visible(fn ($get) => $get('settlement') === ReturnSettlement::REFUND_RECEIVED->value),

                        Textarea::make('reason')
                            ->label('Reason for Return')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Return Items')
                    ->schema([
                        Repeater::make('items')
                            ->schema([
                                Select::make('purchase_item_id')
                                    ->label('Product / Batch')
                                    ->options(function ($get) {
                                        $purchaseId = $get('../../purchase_id');
                                        if (! $purchaseId) {
                                            return [];
                                        }

                                        return PurchaseItem::where('purchase_id', $purchaseId)
                                            ->where('remaining_qty', '>', 0)
                                            ->with('product')
                                            ->get()
                                            ->mapWithKeys(fn ($item) => [
                                                $item->id => "{$item->product->name} (Rem: {$item->remaining_qty}, Rate: ৳{$item->landed_unit_cost})",
                                            ]);
                                    })
                                    ->required()
                                    ->distinct()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set) {
                                        if ($state) {
                                            $batch = PurchaseItem::find($state);
                                            if ($batch) {
                                                $set('unit_price', (string) $batch->landed_unit_cost);
                                                $set('qty', (string) $batch->remaining_qty);
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
                                            $batchId = $get('purchase_item_id');
                                            if (! $batchId) {
                                                return;
                                            }
                                            $batch = PurchaseItem::find($batchId);
                                            if (! $batch) {
                                                return;
                                            }
                                            if (bccomp((string) $value, (string) $batch->remaining_qty, 3) > 0) {
                                                $fail("Quantity cannot exceed remaining batch stock ({$batch->remaining_qty}).");
                                            }
                                        },
                                    ]),

                                TextInput::make('unit_price')
                                    ->label('Credit Rate (৳)')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->required(),
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

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('purchase.invoice_no')
                    ->label('Purchase Bill')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('credit_amount')
                    ->label('Total Credit')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->weight('bold'),

                TextColumn::make('refund_received_amount')
                    ->label('Cash Refund')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state)),

                TextColumn::make('settlement')
                    ->label('Settlement')
                    ->badge(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->url(fn (PurchaseReturn $record): string => route('purchase-returns.receipt', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseReturns::route('/'),
            'create' => CreatePurchaseReturn::route('/create'),
            'view' => ViewPurchaseReturn::route('/{record}'),
        ];
    }
}
