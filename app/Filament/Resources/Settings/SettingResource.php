<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\CreateSetting;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Models\Setting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 10;

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
                TextInput::make('key')
                    ->label('Setting Key')
                    ->required()
                    ->disabled(fn (?Setting $record): bool => $record !== null)
                    ->maxLength(100)
                    ->live()
                    ->helperText(fn (?Setting $record) => match ($record?->key) {
                        'manager_can_add_products' => 'Controls whether Shop Managers can add new products into the system (subject to Super Admin review).',
                        'manager_can_request_restock' => 'Controls whether Shop Managers can submit restock requests to Super Admin.',
                        'manager_sales_visibility' => 'Controls whether Shop Managers see all sales across cashiers or only their own sales.',
                        'site_title' => 'Controls the browser tab title and admin panel brand title.',
                        'favicon' => 'Upload a favicon icon for the browser tab (PNG, ICO, SVG, JPEG/JPG, WEBP).',
                        default => $record?->description,
                    }),

                // Value Type is only shown when creating a new custom setting, NOT when editing existing settings
                Select::make('type')
                    ->label('Value Type')
                    ->options([
                        'string' => 'String / Text',
                        'boolean' => 'Boolean (1 or 0)',
                        'integer' => 'Integer Number',
                        'float' => 'Decimal / Float',
                    ])
                    ->required()
                    ->default('string')
                    ->live()
                    ->visible(fn (?Setting $record): bool => $record === null),

                TextInput::make('description')
                    ->label('Description')
                    ->maxLength(255)
                    ->disabled(fn (?Setting $record): bool => $record !== null)
                    ->visible(fn (?Setting $record): bool => ! in_array($record?->key, [
                        'manager_can_add_products',
                        'manager_can_request_restock',
                        'manager_sales_visibility',
                        'site_title',
                        'favicon',
                    ])),

                // 1. Manager Sales Visibility (Specific clear options)
                Select::make('value')
                    ->label('Manager Sales Visibility')
                    ->options([
                        'all' => 'All Sales — Manager sees all completed sales across all cashiers',
                        'own' => 'Own Sales — Manager sees only sales created by themselves',
                    ])
                    ->visible(fn ($get, ?Setting $record): bool => ($record?->key === 'manager_sales_visibility') || ($get('key') === 'manager_sales_visibility'))
                    ->required(),

                // 2. Manager Can Add Products
                Select::make('value')
                    ->label('Allow Manager to Add Products')
                    ->options([
                        '1' => 'Enabled (Managers can submit new products for Super Admin review)',
                        '0' => 'Disabled (Managers cannot add products)',
                    ])
                    ->visible(fn ($get, ?Setting $record): bool => ($record?->key === 'manager_can_add_products') || ($get('key') === 'manager_can_add_products'))
                    ->required(),

                // 3. Manager Can Request Restock
                Select::make('value')
                    ->label('Allow Manager to Request Restock')
                    ->options([
                        '1' => 'Enabled (Managers can submit restock requests to Super Admin)',
                        '0' => 'Disabled (Managers cannot request restock)',
                    ])
                    ->visible(fn ($get, ?Setting $record): bool => ($record?->key === 'manager_can_request_restock') || ($get('key') === 'manager_can_request_restock'))
                    ->required(),

                // 4. Site Title / Browser Tab Title
                TextInput::make('value')
                    ->label('Site Title / Browser Tab Title')
                    ->placeholder('e.g. Ajmiriganj IT')
                    ->maxLength(100)
                    ->helperText('This title will appear in browser tabs and the admin navigation header.')
                    ->visible(fn ($get, ?Setting $record): bool => ($record?->key === 'site_title') || ($get('key') === 'site_title'))
                    ->required(),

                // 5. Favicon Icon Upload
                FileUpload::make('value')
                    ->label('Favicon Icon')
                    ->helperText('Upload an icon file (PNG, ICO, SVG, JPEG, WEBP) to show in browser tabs. Recommended size: 32x32 or 64x64 pixels. Leave empty to use default favicon.')
                    ->image()
                    ->disk('public')
                    ->directory('settings')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048)
                    ->columnSpanFull()
                    ->visible(fn ($get, ?Setting $record): bool => ($record?->key === 'favicon') || ($get('key') === 'favicon')),

                // 6. Other Boolean settings
                Select::make('value')
                    ->label('Setting Status')
                    ->options([
                        '1' => 'Enabled (1)',
                        '0' => 'Disabled (0)',
                    ])
                    ->visible(fn ($get, ?Setting $record): bool => (($record?->type === 'boolean') || ($get('type') === 'boolean'))
                        && ! in_array($record?->key ?? $get('key'), [
                            'manager_sales_visibility',
                            'manager_can_add_products',
                            'manager_can_request_restock',
                            'site_title',
                            'favicon',
                        ])
                    )
                    ->required(),

                // 7. General text / integer / decimal settings
                Textarea::make('value')
                    ->label('Setting Value')
                    ->rows(3)
                    ->columnSpanFull()
                    ->visible(fn ($get, ?Setting $record): bool => (($record?->type !== 'boolean') && ($get('type') !== 'boolean'))
                        && ! in_array($record?->key ?? $get('key'), [
                            'manager_sales_visibility',
                            'manager_can_add_products',
                            'manager_can_request_restock',
                            'site_title',
                            'favicon',
                        ])
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Setting')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Value')
                    ->state(function (Setting $record) {
                        if ($record->key === 'manager_sales_visibility') {
                            return $record->value === 'all' ? 'All Sales' : 'Own Sales Only';
                        }
                        if (in_array($record->key, ['manager_can_add_products', 'manager_can_request_restock']) || $record->type === 'boolean') {
                            return (bool) $record->value ? 'Enabled' : 'Disabled';
                        }
                        if ($record->key === 'favicon') {
                            return ! empty($record->value) ? 'Custom Favicon Uploaded' : 'Default Favicon';
                        }

                        return $record->value;
                    })
                    ->badge(fn (Setting $record) => in_array($record->key, ['manager_can_add_products', 'manager_can_request_restock', 'favicon']) || $record->type === 'boolean')
                    ->color(fn ($state, Setting $record) => match (true) {
                        $record->key === 'favicon' => (! empty($record->value)) ? 'success' : 'gray',
                        (in_array($record->key, ['manager_can_add_products', 'manager_can_request_restock']) || $record->type === 'boolean') => (bool) $record->value ? 'success' : 'gray',
                        default => null,
                    })
                    ->icon(fn (Setting $record) => $record->key === 'favicon' ? (! empty($record->value) ? 'heroicon-m-photo' : 'heroicon-m-globe-alt') : null)
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->label('Type')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettings::route('/'),
            'create' => CreateSetting::route('/create'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}
