<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountCategories;

use App\Enums\AccountCategoryType;
use App\Filament\Resources\AccountCategories\Pages\CreateAccountCategory;
use App\Filament\Resources\AccountCategories\Pages\EditAccountCategory;
use App\Filament\Resources\AccountCategories\Pages\ListAccountCategories;
use App\Models\AccountCategory;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccountCategoryResource extends Resource
{
    protected static ?string $model = AccountCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';

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
            ->columns(1)
            ->components([
                Section::make('Category Details')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?AccountCategory $record) => $record?->isSystem() ?? false),

                        Select::make('type')
                            ->label('Category Type')
                            ->options([
                                AccountCategoryType::EXPENSE->value => 'Expense',
                                AccountCategoryType::INCOME->value => 'Income',
                                AccountCategoryType::EQUITY->value => 'Equity',
                                AccountCategoryType::TRANSFER->value => 'Transfer',
                            ])
                            ->required()
                            ->disabled(fn (?AccountCategory $record) => $record?->isSystem() ?? false),

                        Toggle::make('affects_profit')
                            ->label('Affects Net Profit (Operating Expense / Other Income)')
                            ->helperText('Enable for operational expenses (Rent, Electricity, Salary) and other income. Disable for vendor payment, equity and transfers.')
                            ->default(true)
                            ->disabled(fn (?AccountCategory $record) => $record?->isSystem() ?? false),

                        Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Category Name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AccountCategoryType ? $state->label() : strtoupper((string) $state))
                    ->color(fn ($state) => match ($state instanceof AccountCategoryType ? $state->value : (string) $state) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'equity' => 'warning',
                        'transfer' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('affects_profit')
                    ->label('Affects Profit')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('is_system')
                    ->label('Classification')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'System (Locked)' : 'Custom')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        AccountCategoryType::EXPENSE->value => 'Expense',
                        AccountCategoryType::INCOME->value => 'Income',
                        AccountCategoryType::EQUITY->value => 'Equity',
                        AccountCategoryType::TRANSFER->value => 'Transfer',
                    ]),

                TernaryFilter::make('is_system')
                    ->label('System Category'),

                TernaryFilter::make('affects_profit')
                    ->label('Affects Profit'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (AccountCategory $record) => $record->isSystem() || $record->transactions()->exists()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountCategories::route('/'),
            'create' => CreateAccountCategory::route('/create'),
            'edit' => EditAccountCategory::route('/{record}/edit'),
        ];
    }
}
