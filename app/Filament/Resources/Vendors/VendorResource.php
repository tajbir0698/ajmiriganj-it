<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorAccountService;
use App\Services\VendorPaymentService;
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
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 1;

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
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function canDelete(Model $record): bool
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
                    ->label('Vendor / Company Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('contact_person')
                    ->label('Contact Person')
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label('Primary Phone')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('alt_phone')
                    ->label('Alternative Phone')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->maxLength(100),
                TextInput::make('opening_balance')
                    ->label('Opening Due Balance')
                    ->numeric()
                    ->prefix('৳')
                    ->default(0.00)
                    ->helperText('Initial due amount owed to this vendor before system adoption.'),
                Textarea::make('address')
                    ->label('Office / Shop Address')
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Internal Note')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active Status')
                    ->default(true),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('vendor_account')
                    ->label('')
                    ->view('filament.resources.vendors.vendor-account')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Vendor Name')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->label('Contact Person')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                TextColumn::make('current_due')
                    ->label('Current Due')
                    ->weight('semibold')
                    ->formatStateUsing(fn (Vendor $record): string => Money::format($record->current_due))
                    ->color(fn (Vendor $record): string => (float) $record->current_due > 0 ? 'danger' : 'success'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_due')
                    ->label('Has Outstanding Due')
                    ->query(fn (Builder $query): Builder => $query->whereHas('purchases', fn ($q) => $q->where('due_amount', '>', 0))),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('make_payment')
                    ->label('Make Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label('Payment Amount (৳)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01),
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                            ->default(PaymentMethod::CASH->value)
                            ->required(),
                        Select::make('account_id')
                            ->label('Paid From Account')
                            ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                            ->required(),
                        DatePicker::make('payment_date')
                            ->label('Date')
                            ->default(now()->toDateString())
                            ->required(),
                        TextInput::make('reference_no')
                            ->label('Reference / Cheque No')
                            ->maxLength(100),
                        Textarea::make('note')
                            ->label('Note')
                            ->rows(2),
                    ])
                    ->action(function (Vendor $record, array $data, VendorPaymentService $service): void {
                        try {
                            $account = Account::findOrFail($data['account_id']);
                            $method = PaymentMethod::from($data['payment_method']);
                            $service->pay(
                                vendor: $record,
                                amount: (string) $data['amount'],
                                method: $method,
                                account: $account,
                                date: $data['payment_date'],
                                reference: $data['reference_no'] ?? null,
                                note: $data['note'] ?? null,
                                allocation: 'auto'
                            );
                            Notification::make()->title('Payment recorded successfully')->success()->send();
                        } catch (\App\Exceptions\InsufficientFundsException $e) {
                            Notification::make()
                                ->title('Insufficient Funds')
                                ->body("Account {$e->account->name} has balance of ৳ {$e->currentBalance}, which is not enough for this payment.")
                                ->danger()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Payment Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/create'),
            'view' => ViewVendor::route('/{record}'),
            'edit' => EditVendor::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
