<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\CustomerPaymentService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),

                TextInput::make('opening_balance')
                    ->label('Opening Due Balance (৳)')
                    ->numeric()
                    ->default('0.00'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Textarea::make('address')
                    ->rows(2)
                    ->columnSpanFull(),

                Textarea::make('note')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\ViewEntry::make('customer_account')
                    ->label('')
                    ->view('filament.resources.customers.customer-account')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->searchable()
                    ->default('-'),

                TextColumn::make('opening_balance')
                    ->label('Opening Due')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->sortable(),

                TextColumn::make('current_due')
                    ->label('Current Due')
                    ->alignRight()
                    ->state(fn (Customer $record): string => app(CustomerAccountService::class)->getCurrentDue($record))
                    ->formatStateUsing(fn ($state): string => Money::format((string) $state))
                    ->weight('bold')
                    ->color(fn ($state): string => bccomp((string) $state, '0.00', 2) > 0 ? 'danger' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('collect_due')
                    ->label('Collect Due')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label('Collection Amount (৳)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01),
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                            ->default(PaymentMethod::CASH->value)
                            ->required(),
                        Select::make('account_id')
                            ->label('Deposit Account')
                            ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                            ->required(),
                        DatePicker::make('payment_date')
                            ->label('Date')
                            ->default(now()->toDateString())
                            ->required(),
                        TextInput::make('reference_no')
                            ->label('Reference / Trx ID')
                            ->maxLength(100),
                        Textarea::make('note')
                            ->label('Note')
                            ->rows(2),
                    ])
                    ->action(function (Customer $record, array $data, CustomerPaymentService $service): void {
                        try {
                            $account = Account::findOrFail($data['account_id']);
                            $method = PaymentMethod::from($data['payment_method']);
                            $service->collect(
                                customer: $record,
                                amount: (string) $data['amount'],
                                method: $method,
                                account: $account,
                                date: $data['payment_date'],
                                reference: $data['reference_no'] ?? null,
                                note: $data['note'] ?? null,
                                allocation: 'auto'
                            );
                            Notification::make()->title('Due collected successfully')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Collection Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => \App\Filament\Resources\Customers\Pages\ViewCustomerAccount::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
