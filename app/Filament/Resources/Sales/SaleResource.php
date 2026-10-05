<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false; // Sales are created via POS (/pos)
    }

    public static function canEdit($record): bool
    {
        return false; // Sales are immutable
    }

    public static function canDelete($record): bool
    {
        return false; // Sales cannot be deleted directly
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'creator', 'items.batches']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // 1. Sale Overview (Full Width Stack)
                Section::make('Sale Overview')
                    ->icon('heroicon-o-shopping-bag')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('invoice_no')
                            ->label('Invoice Number')
                            ->weight(FontWeight::Bold)
                            ->copyable()
                            ->copyMessage('Invoice number copied')
                            ->icon('heroicon-o-document-text'),

                        TextEntry::make('sale_date')
                            ->label('Sale Date & Time')
                            ->dateTime('d M Y, h:i A')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->default('Walk-in Customer')
                            ->icon('heroicon-o-user')
                            ->helperText(fn (Sale $record): ?string => $record->customer?->phone ?: 'No phone recorded'),

                        TextEntry::make('creator.name')
                            ->label('Cashier / Staff')
                            ->default('System')
                            ->icon('heroicon-o-identification'),
                    ]),

                // 2. Financials (Full Width Stack)
                Section::make('Payment & Financials')
                    ->icon('heroicon-o-banknotes')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('total')
                            ->label('Total Payable')
                            ->formatStateUsing(fn ($state): string => Money::format((string) $state))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color('primary'),

                        TextEntry::make('paid_amount')
                            ->label('Paid Amount')
                            ->formatStateUsing(fn ($state): string => Money::format((string) $state))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color('success'),

                        TextEntry::make('due_amount')
                            ->label('Due Amount')
                            ->formatStateUsing(fn ($state): string => Money::format((string) $state))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color(fn (Sale $record): string => bccomp((string) $record->due_amount, '0.00', 2) > 0 ? 'danger' : 'gray'),

                        TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof PaymentMethod ? $state->label() : ($state ? strtoupper((string) $state) : 'None'))
                            ->color(fn ($state) => match ($state instanceof PaymentMethod ? $state->value : (string) $state) {
                                'cash' => 'success',
                                'bkash', 'nagad' => 'info',
                                'bank' => 'warning',
                                default => 'gray',
                            }),
                    ]),

                // 3. Profit & Margin Analysis (Full Width Stack - Super Admin ONLY)
                Section::make('Profit & Margin Analysis')
                    ->description('Visible only to Super Admin (confidential)')
                    ->icon('heroicon-o-chart-bar')
                    ->columnSpanFull()
                    ->columns(3)
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin())
                    ->schema([
                        TextEntry::make('gross_profit')
                            ->label('Gross Profit')
                            ->formatStateUsing(fn ($state): string => Money::format((string) $state))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),

                        TextEntry::make('discount')
                            ->label('Overall Discount')
                            ->formatStateUsing(fn ($state): string => '-' . Money::format((string) $state))
                            ->color('warning'),

                        TextEntry::make('net_profit')
                            ->label('Net Profit')
                            ->formatStateUsing(fn ($state): string => Money::format((string) $state))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color(fn ($state): string => bccomp((string) $state, '0.00', 2) >= 0 ? 'success' : 'danger'),
                    ]),

                // 4. Sold Items (Full Width Stack - Raw Table)
                Section::make('Sold Items')
                    ->icon('heroicon-o-cube')
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('items_table')
                            ->label('')
                            ->view('filament.resources.sales.items-table')
                            ->columnSpanFull(),
                    ]),

                // 5. Notes (Full Width Stack, if any)
                Section::make('Notes')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->columnSpanFull()
                    ->visible(fn (Sale $record): bool => ! empty($record->note))
                    ->schema([
                        TextEntry::make('note')
                            ->label('')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $showCashier = $isSuperAdmin || Setting::get('manager_sales_visibility', 'all') === 'all';

        $columns = [
            TextColumn::make('invoice_no')
                ->label('Invoice #')
                ->weight('bold')
                ->searchable()
                ->sortable(),

            TextColumn::make('sale_date')
                ->label('Date')
                ->date('d M Y')
                ->sortable(),

            TextColumn::make('customer.name')
                ->label('Customer')
                ->searchable()
                ->default('Walk-in Customer'),

            TextColumn::make('total')
                ->label('Total')
                ->alignRight()
                ->formatStateUsing(fn ($state) => Money::format((string) $state))
                ->sortable(),

            TextColumn::make('paid_amount')
                ->label('Paid')
                ->alignRight()
                ->formatStateUsing(fn ($state) => Money::format((string) $state))
                ->color('success')
                ->sortable(),

            TextColumn::make('due_amount')
                ->label('Due')
                ->alignRight()
                ->formatStateUsing(fn ($state) => Money::format((string) $state))
                ->color(fn (Sale $record): string => bccomp((string) $record->due_amount, '0.00', 2) > 0 ? 'danger' : 'gray')
                ->sortable(),

            TextColumn::make('payment_method')
                ->label('Method')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof PaymentMethod ? $state->label() : strtoupper((string) $state))
                ->color(fn ($state) => match ($state instanceof PaymentMethod ? $state->value : (string) $state) {
                    'cash' => 'success',
                    'bkash', 'nagad' => 'info',
                    'bank' => 'warning',
                    default => 'gray',
                }),

            TextColumn::make('creator.name')
                ->label('Cashier')
                ->sortable()
                ->visible($showCashier),
        ];

        if ($isSuperAdmin) {
            $columns[] = TextColumn::make('net_profit')
                ->label('Profit (৳)')
                ->alignRight()
                ->formatStateUsing(fn ($state) => Money::format((string) $state))
                ->color(fn ($state): string => bccomp((string) $state, '0.00', 2) >= 0 ? 'success' : 'danger')
                ->weight('bold')
                ->sortable();
        }

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($isSuperAdmin) {
                if (! $isSuperAdmin) {
                    $query->where('status', SaleStatus::COMPLETED);
                    if (Setting::get('manager_sales_visibility', 'all') === 'own') {
                        $query->where('created_by', auth()->id());
                    }
                }
            })
            ->columns($columns)
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('sale_date')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('sale_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('sale_date', '<=', $date));
                    }),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name'),

                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'bkash' => 'bKash',
                        'nagad' => 'Nagad',
                        'bank' => 'Bank Transfer',
                    ]),

                Filter::make('has_due')
                    ->label('Has Due Amount')
                    ->query(fn (Builder $query): Builder => $query->where('due_amount', '>', 0)),

                SelectFilter::make('created_by')
                    ->label('Cashier')
                    ->relationship('creator', 'name')
                    ->visible($showCashier),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('reprint')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Sale $record): string => route('sales.receipt', ['sale' => $record, 'reprint' => 1]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'view' => ViewSale::route('/{record}'),
        ];
    }
}
