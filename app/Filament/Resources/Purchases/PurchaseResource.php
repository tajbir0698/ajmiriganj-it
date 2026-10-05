<?php

declare(strict_types=1);

namespace App\Filament\Resources\Purchases;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Pages\ViewPurchase;
use App\Models\Account;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 2;

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
        return false; // Purchases are immutable; cancel & recreate to correct mistakes
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Hard-delete not allowed; use cancelPurchase
    }

    public static function form(Schema $schema): Schema
    {
        $defaultMargin = (float) Setting::get('default_target_margin_percent', 25);

        return $schema
            ->columns(1)
            ->components([
                // 1. Vendor & Invoice Information (Full Width)
                Section::make('Vendor & Invoice Information')
                    ->description('Specify the supplier and invoice reference details')
                    ->icon('heroicon-o-building-storefront')
                    ->columns(3)
                    ->schema([
                        Select::make('vendor_id')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Vendor / Supplier'),

                        DatePicker::make('purchase_date')
                            ->label('Purchase Date')
                            ->default(now())
                            ->required(),

                        TextInput::make('vendor_invoice_no')
                            ->label('Vendor Bill / Memo #')
                            ->placeholder('e.g. ST-2026-901'),
                    ]),

                // 2. Purchase Items (Stock In & FIFO Batches) (Full Width)
                Section::make('Purchase Items (Stock In & FIFO Batches)')
                    ->description('Products entered here form individual FIFO batches with distinct unit costs.')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Repeater::make('items')
                            ->label('Items')
                            ->table([
                                TableColumn::make('Product')
                                    ->width('35%')
                                    ->markAsRequired(),
                                TableColumn::make('Quantity')
                                    ->width('14%')
                                    ->markAsRequired(),
                                TableColumn::make('Unit Cost (৳)')
                                    ->width('17%')
                                    ->markAsRequired(),
                                TableColumn::make('Target Margin %')
                                    ->width('16%'),
                                TableColumn::make('Suggested Sale Price (৳)')
                                    ->width('18%'),
                            ])
                            ->compact()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => Product::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($defaultMargin): void {
                                        if ($product = Product::find($state)) {
                                            $lastCost = (float) $product->last_cost;
                                            $set('unit_cost', $lastCost > 0 ? $lastCost : 0.00);

                                            $targetMargin = (float) ($get('target_margin') ?: $defaultMargin);
                                            $suggested = $lastCost * (1 + $targetMargin / 100);
                                            $set('new_sale_price', round($suggested));
                                        }
                                    }),

                                TextInput::make('qty')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.001)
                                    ->required()
                                    ->live(onBlur: true),

                                TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('৳')
                                    ->default(0.00)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($defaultMargin): void {
                                        $cost = (float) $state;
                                        $targetMargin = (float) ($get('target_margin') ?: $defaultMargin);
                                        $suggested = $cost * (1 + $targetMargin / 100);
                                        $set('new_sale_price', round($suggested));
                                    }),

                                TextInput::make('target_margin')
                                    ->label('Target Margin %')
                                    ->numeric()
                                    ->default($defaultMargin)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        $cost = (float) ($get('unit_cost') ?: 0);
                                        $margin = (float) $state;
                                        $suggested = $cost * (1 + $margin / 100);
                                        $set('new_sale_price', round($suggested));
                                    }),

                                TextInput::make('new_sale_price')
                                    ->label('New Sale Price')
                                    ->numeric()
                                    ->prefix('৳')
                                    ->live(onBlur: true),
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add Product')
                            ->required(),
                    ]),

                // 3. Payment & Documents (Full Width)
                Section::make('Payment & Documents')
                    ->description('Record payment method, account, and attach supplier bill')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->schema([
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                PaymentMethod::CASH->value => 'Cash',
                                PaymentMethod::BKASH->value => 'bKash',
                                PaymentMethod::NAGAD->value => 'Nagad',
                                PaymentMethod::BANK->value => 'Bank Transfer',
                                PaymentMethod::CHEQUE->value => 'Cheque',
                            ])
                            ->default(PaymentMethod::CASH->value)
                            ->required(),

                        Select::make('account_id')
                            ->label('Payment Account')
                            ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                            ->placeholder('Select payment account')
                            ->hint('Required if paying > ৳0'),

                        TextInput::make('reference_no')
                            ->label('Transaction Reference #')
                            ->placeholder('e.g. TrxID / Cheque #'),

                        Textarea::make('note')
                            ->label('Purchase Memo Note')
                            ->placeholder('Any internal notes, terms, or remarks...')
                            ->rows(2)
                            ->columnSpanFull(),

                        FileUpload::make('bill_files')
                            ->label('Bill / Receipt Uploads')
                            ->multiple()
                            ->disk('private')
                            ->directory('attachments/purchases')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(5120)
                            ->hint('Max 5MB per file (JPG, PNG, WebP, PDF)')
                            ->columnSpanFull(),
                    ]),

                // 4. Settlement & Landed Costs (Full Width)
                Section::make('Settlement & Landed Costs')
                    ->description('Landed cost adjustments, cash settlement, and real-time financial summary')
                    ->icon('heroicon-o-calculator')
                    ->columns(3)
                    ->schema([
                        TextInput::make('shipping_cost')
                            ->label('Shipping / Freight (+)')
                            ->numeric()
                            ->prefix('৳')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->hint('Adds to landed unit cost'),

                        TextInput::make('discount')
                            ->label('Invoice Discount (-)')
                            ->numeric()
                            ->prefix('৳')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->hint('Deducts from unit cost'),

                        TextInput::make('paid_amount')
                            ->label('Paid Amount (Cash Out)')
                            ->numeric()
                            ->prefix('৳')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->hint('Unpaid balance = Due'),

                        Placeholder::make('invoice_summary')
                            ->label('Live Financial Breakdown')
                            ->columnSpanFull()
                            ->content(function ($get): HtmlString {
                                $items = $get('items') ?? [];
                                $subtotal = '0.00';
                                foreach ($items as $item) {
                                    $qty = (string) ($item['qty'] ?? '0');
                                    $cost = (string) ($item['unit_cost'] ?? '0');
                                    $subtotal = bcadd($subtotal, bcmul($qty, $cost, 4), 2);
                                }

                                $shipping = (string) ($get('shipping_cost') ?: '0.00');
                                $discount = (string) ($get('discount') ?: '0.00');
                                $paid = (string) ($get('paid_amount') ?: '0.00');

                                $total = bcsub(bcadd($subtotal, $shipping, 2), $discount, 2);
                                $due = bcsub($total, $paid, 2);

                                $html = '<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-1">';
                                $html .= '  <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3 bg-gray-50/70 dark:bg-gray-900/60 shadow-xs"><span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">Items Subtotal</span><span class="text-base font-bold text-gray-900 dark:text-white mt-1 block">'.Money::format($subtotal).'</span></div>';
                                $html .= '  <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3 bg-gray-50/70 dark:bg-gray-900/60 shadow-xs"><span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">Shipping (+)</span><span class="text-base font-bold text-gray-900 dark:text-white mt-1 block">'.Money::format($shipping).'</span></div>';
                                $html .= '  <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3 bg-gray-50/70 dark:bg-gray-900/60 shadow-xs"><span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">Discount (-)</span><span class="text-base font-bold text-gray-900 dark:text-white mt-1 block">'.Money::format($discount).'</span></div>';
                                $html .= '  <div class="rounded-xl border border-primary-300 dark:border-primary-800 p-3 bg-primary-50/40 dark:bg-primary-950/40 shadow-xs"><span class="text-xs font-semibold text-primary-600 dark:text-primary-400 block">Grand Total</span><span class="text-base font-extrabold text-primary-600 dark:text-primary-400 mt-1 block">'.Money::format($total).'</span></div>';
                                $html .= '  <div class="rounded-xl border border-emerald-300 dark:border-emerald-800 p-3 bg-emerald-50/40 dark:bg-emerald-950/40 shadow-xs"><span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 block">Paid Amount</span><span class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 block">'.Money::format($paid).'</span></div>';
                                $html .= '  <div class="rounded-xl border border-rose-300 dark:border-rose-800 p-3 bg-rose-50/40 dark:bg-rose-950/40 shadow-xs"><span class="text-xs font-semibold text-rose-600 dark:text-rose-400 block">Balance Due</span><span class="text-base font-extrabold text-rose-600 dark:text-rose-400 mt-1 block">'.Money::format($due).'</span></div>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('purchase_view')
                    ->label('')
                    ->view('filament.resources.purchases.view-purchase')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Invoice #')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('purchase_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->color('success')
                    ->sortable(),

                TextColumn::make('due_amount')
                    ->label('Due')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->color(fn (Purchase $record): string => (float) $record->due_amount > 0 ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::PAID => 'success',
                        PaymentStatus::PARTIAL => 'warning',
                        PaymentStatus::DUE => 'danger',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (PurchaseStatus $state): string => match ($state) {
                        PurchaseStatus::ACTIVE => 'success',
                        PurchaseStatus::CANCELLED => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->label('Vendor'),

                SelectFilter::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'due' => 'Due',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'cancelled' => 'Cancelled',
                    ]),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $from) => $q->whereDate('purchase_date', '>=', $from))
                            ->when($data['until'], fn (Builder $q, $until) => $q->whereDate('purchase_date', '<=', $until));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(false),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }
}
