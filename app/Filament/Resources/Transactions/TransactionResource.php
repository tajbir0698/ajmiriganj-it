<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transactions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Pages\ViewTransaction;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Transaction Overview')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('voucher_no')
                            ->label('Voucher #')
                            ->default(fn (Transaction $record) => "TRX-{$record->id}")
                            ->weight(FontWeight::Bold),

                        TextEntry::make('date')
                            ->label('Date')
                            ->date('d M Y'),

                        TextEntry::make('account.name')
                            ->label('Account')
                            ->weight(FontWeight::Bold),

                        TextEntry::make('category.name')
                            ->label('Category')
                            ->default('General'),

                        TextEntry::make('type')
                            ->label('Direction')
                            ->badge()
                            ->formatStateUsing(fn ($state) => strtoupper($state instanceof TransactionType ? $state->value : (string) $state))
                            ->color(fn ($state) => ($state instanceof TransactionType ? $state->value : (string) $state) === 'in' ? 'success' : 'danger'),

                        TextEntry::make('amount')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state) => Money::format((string) $state))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),

                        TextEntry::make('source')
                            ->label('Source')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof TransactionSource ? $state->label() : strtoupper((string) $state)),

                        TextEntry::make('creator.name')
                            ->label('Recorded By')
                            ->default('System'),

                        TextEntry::make('description')
                            ->label('Description / Memo')
                            ->columnSpanFull(),
                    ]),

                Section::make('Reversal Information')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->columnSpanFull()
                    ->visible(fn (Transaction $record) => $record->isReversed() || $record->isReversal())
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reversed_at')
                            ->label('Reversed At')
                            ->dateTime('d M Y, h:i A')
                            ->visible(fn (Transaction $record) => $record->isReversed()),

                        TextEntry::make('reversal_reason')
                            ->label('Reason for Reversal')
                            ->visible(fn (Transaction $record) => $record->isReversed()),

                        TextEntry::make('reversalOf.voucher_no')
                            ->label('Original Transaction')
                            ->visible(fn (Transaction $record) => $record->isReversal()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('voucher_no')
                    ->label('Voucher #')
                    ->fontFamily('mono')
                    ->weight(FontWeight::Bold)
                    ->default(fn (Transaction $record) => "TRX-{$record->id}")
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->default('General')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Direction')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state instanceof TransactionType ? $state->value : (string) $state))
                    ->color(fn ($state) => ($state instanceof TransactionType ? $state->value : (string) $state) === 'in' ? 'success' : 'danger'),

                TextColumn::make('amount')
                    ->label('Amount (৳)')
                    ->alignRight()
                    ->weight(FontWeight::Bold)
                    ->formatStateUsing(fn ($state, Transaction $record) => ($record->type === TransactionType::IN ? '+' : '-').Money::format((string) $state))
                    ->color(fn (Transaction $record) => $record->type === TransactionType::IN ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(35)
                    ->searchable(),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TransactionSource ? $state->label() : strtoupper((string) $state))
                    ->color(fn ($state) => match ($state instanceof TransactionSource ? $state->value : (string) $state) {
                        'system' => 'gray',
                        'manual' => 'info',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Transaction $record): string => $record->isReversed() ? 'Reversed' : ($record->isReversal() ? 'Reversal' : 'Active'))
                    ->color(fn (string $state): string => match ($state) {
                        'Reversed' => 'danger',
                        'Reversal' => 'warning',
                        default => 'success',
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $from) => $q->whereDate('date', '>=', $from))
                            ->when($data['until'], fn (Builder $q, $until) => $q->whereDate('date', '<=', $until));
                    }),

                SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship('account', 'name'),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                SelectFilter::make('type')
                    ->options([
                        'in' => 'Money In (+)',
                        'out' => 'Money Out (-)',
                    ]),

                SelectFilter::make('source')
                    ->options([
                        TransactionSource::MANUAL->value => 'Manual Entry',
                        TransactionSource::SYSTEM->value => 'System Generated',
                        TransactionSource::TRANSFER->value => 'Account Transfer',
                    ]),

                Filter::make('active_only')
                    ->label('Exclude Reversed')
                    ->query(fn (Builder $query): Builder => $query->whereNull('reversed_at')->whereNull('reversal_of_id')),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('reverse')
                    ->label('Reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Transaction $record): bool => ! $record->isReversed() && ! $record->isReversal() && $record->source !== TransactionSource::SYSTEM)
                    ->requiresConfirmation()
                    ->modalHeading('Reverse Transaction')
                    ->modalDescription('This will create an opposite compensatory transaction, restoring the account balance. Please state the reason for this reversal.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Reversal Reason')
                            ->required()
                            ->placeholder('e.g. Incorrect amount entered / duplicated entry')
                            ->rows(3),
                    ])
                    ->action(function (Transaction $record, array $data, AccountService $accountService): void {
                        try {
                            $accountService->reverse($record, $data['reason']);
                            Notification::make()
                                ->title('Transaction reversed successfully')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to reverse transaction')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'view' => ViewTransaction::route('/{record}'),
        ];
    }
}
