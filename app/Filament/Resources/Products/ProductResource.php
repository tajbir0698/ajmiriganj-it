<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user?->isSuperAdmin()) {
            return null;
        }

        $count = Product::where('needs_review', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),
                TextInput::make('barcode')
                    ->label('Barcode')
                    ->maxLength(100),
                TextInput::make('name')
                    ->label('Product Name')
                    ->required()
                    ->maxLength(255),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('unit_id')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('brand')
                    ->label('Brand')
                    ->maxLength(100),
                Textarea::make('description')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->image()
                    ->directory('products')
                    ->disk('public'),
                TextInput::make('last_cost')
                    ->label('Purchase Cost')
                    ->numeric()
                    ->prefix('৳')
                    ->default(0.00)
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin())
                    ->required(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                TextInput::make('sale_price')
                    ->label('Sale Price')
                    ->required()
                    ->numeric()
                    ->prefix('৳')
                    ->default(0.00),
                TextInput::make('stock_qty')
                    ->label('Stock Quantity')
                    ->numeric()
                    ->default(0.000)
                    ->disabled(fn (?Product $record): bool => $record !== null)
                    ->helperText('Opening quantity on create. Controlled by purchases & sales after creation.'),
                TextInput::make('alert_qty')
                    ->label('Low Stock Alert Threshold')
                    ->required()
                    ->numeric()
                    ->default(5.000),
                Toggle::make('is_active')
                    ->label('Active Status')
                    ->default(true),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sku')
                    ->label('SKU'),
                TextEntry::make('barcode')
                    ->label('Barcode')
                    ->placeholder('-'),
                TextEntry::make('name')
                    ->label('Product Name'),
                TextEntry::make('category.name')
                    ->label('Category')
                    ->placeholder('-'),
                TextEntry::make('unit.name')
                    ->label('Unit')
                    ->placeholder('-'),
                TextEntry::make('brand')
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                ImageEntry::make('image')
                    ->placeholder('-'),
                TextEntry::make('last_cost')
                    ->label('Purchase Cost')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                TextEntry::make('sale_price')
                    ->label('Sale Price')
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                TextEntry::make('stock_qty')
                    ->label('Current Stock')
                    ->formatStateUsing(fn ($state, Product $record): string => Money::formatQty($state).' '.($record->unit?->short_name ?? '')),
                TextEntry::make('alert_qty')
                    ->label('Alert Threshold')
                    ->formatStateUsing(fn ($state, Product $record): string => Money::formatQty($state).' '.($record->unit?->short_name ?? '')),
                IconEntry::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Product $record): bool => $record->trashed()),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('creator.name')
                    ->label('Created By')
                    ->placeholder('System / Admin')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                IconEntry::make('needs_review')
                    ->label('Needs Review')
                    ->boolean()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                ViewEntry::make('batches_and_history')
                    ->label('')
                    ->view('filament.resources.products.batches-and-history')
                    ->columnSpanFull()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                TextColumn::make('last_cost')
                    ->label('Purchase Cost')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                TextColumn::make('stock_qty')
                    ->label('Stock Qty')
                    ->sortable()
                    ->formatStateUsing(fn ($state, Product $record): string => Money::formatQty($state).' '.($record->unit?->short_name ?? ''))
                    ->color(fn (Product $record): string => $record->isLowStock() ? 'danger' : 'success'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('needs_review')
                    ->label('Needs Review')
                    ->boolean()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
                TernaryFilter::make('needs_review')
                    ->label('Needs Review')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('clear_review')
                    ->label('Approve Review')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => (bool) auth()->user()?->isSuperAdmin() && (bool) $record->needs_review)
                    ->action(function (Product $record) {
                        $record->update(['needs_review' => false]);
                        Notification::make()->title('Product review flag cleared')->success()->send();
                    }),
                EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                ])->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
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
