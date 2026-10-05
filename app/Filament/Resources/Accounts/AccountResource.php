<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts;

use App\Enums\AccountKind;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Account Information')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Account Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Select::make('type')
                            ->label('Account Kind')
                            ->options([
                                AccountKind::CASH->value => 'Cash in Hand',
                                AccountKind::BANK->value => 'Bank Account',
                                AccountKind::MOBILE_WALLET->value => 'Mobile Financial Service (MFS)',
                                AccountKind::OTHER->value => 'Other Account',
                            ])
                            ->default(AccountKind::CASH->value)
                            ->required(),

                        TextInput::make('account_number')
                            ->label('Account / Card / Phone #')
                            ->maxLength(255)
                            ->placeholder('e.g. 1029384756 or 017xxxxxxxx'),

                        TextInput::make('opening_balance')
                            ->label('Opening Balance')
                            ->numeric()
                            ->prefix('৳')
                            ->default(0.00)
                            ->required()
                            ->rules([
                                fn ($get, ?Account $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    if ($record && $record->transactions()->exists()) {
                                        if (bccomp((string) $record->opening_balance, (string) $value, 2) !== 0) {
                                            if (! $get('confirm_opening_balance_change')) {
                                                $fail('You must check the confirmation box to change the opening balance of an account with existing transactions.');
                                            }
                                        }
                                    }
                                },
                            ]),

                        Checkbox::make('confirm_opening_balance_change')
                            ->label('I confirm altering the opening balance of an account with existing transactions')
                            ->dehydrated(false)
                            ->visible(fn (?Account $record) => $record && $record->transactions()->exists())
                            ->columnSpanFull(),

                        Textarea::make('note')
                            ->label('Internal Note / Details')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // 1. Account Summary Card
                Section::make('Account Overview')
                    ->icon('heroicon-o-building-library')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Account Name')
                            ->weight(FontWeight::Bold),

                        TextEntry::make('type')
                            ->label('Kind')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof AccountKind ? $state->label() : strtoupper((string) $state)),

                        TextEntry::make('account_number')
                            ->label('Account #')
                            ->default('N/A'),

                        TextEntry::make('current_balance')
                            ->label('Live Balance')
                            ->state(fn (Account $record, AccountService $accountService) => $accountService->balance($record))
                            ->formatStateUsing(fn ($state) => Money::format((string) $state))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color(fn ($state) => bccomp((string) $state, '0.00', 2) >= 0 ? 'success' : 'danger'),
                    ]),

                // 2. Ledger Section
                Section::make('Account Ledger')
                    ->icon('heroicon-o-book-open')
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('ledger_view')
                            ->label('')
                            ->view('filament.resources.accounts.ledger-view')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Account Name')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AccountKind ? $state->label() : strtoupper((string) $state))
                    ->color(fn ($state) => match ($state instanceof AccountKind ? $state->value : (string) $state) {
                        'cash' => 'success',
                        'bank' => 'info',
                        'mobile_wallet' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('account_number')
                    ->label('Account #')
                    ->default('—')
                    ->searchable(),

                TextColumn::make('opening_balance')
                    ->label('Opening (৳)')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->sortable(),

                TextColumn::make('current_balance')
                    ->label('Current Balance (৳)')
                    ->alignRight()
                    ->weight(FontWeight::Bold)
                    ->state(function (Account $record): string {
                        static $balances = null;
                        if ($balances === null) {
                            $balances = app(AccountService::class)->balances();
                        }
                        return $balances->get($record->id, '0.00');
                    })
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->color(fn ($state) => bccomp((string) $state, '0.00', 2) >= 0 ? 'success' : 'danger'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        AccountKind::CASH->value => 'Cash in Hand',
                        AccountKind::BANK->value => 'Bank Account',
                        AccountKind::MOBILE_WALLET->value => 'Mobile Financial Service',
                        AccountKind::OTHER->value => 'Other Account',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Account $record) => $record->transactions()->exists()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
